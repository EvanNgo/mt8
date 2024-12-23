# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package LicenseVerification::App::CMS;

use strict;
use warnings;
use MT::Util;
use MT::Util::Encode;
use MT::PluginData;
use HTTP::Request::Common;

sub edit {
    my ($app, $param) = @_;

    return $app->permission_denied()
        if !$app->user->is_superuser;

    my $plugin  = $app->component('licenseverification');
    my $pd      = MT::PluginData->get_by_key({ plugin => $plugin->id, key => $plugin->id, });
    my $pd_data = (ref $pd->data eq 'HASH') ? $pd->data : {};
    if ($pd_data->{key}) {
        $param->{has_pd_data}           = 1;
        $param->{license_key}           = $pd_data->{key};
        $param->{said}                  = $pd_data->{said};
        $param->{licensee_name}         = $pd_data->{name};
        $param->{licensed_product_name} = $pd_data->{product};
        $param->{registered_on}         = MT::Util::format_ts('%Y-%m-%d', MT::Util::epoch2ts(undef, $pd_data->{registered_on}), undef, 'en', 0);
        $param->{support_expired_on}    = MT::Util::format_ts('%Y-%m-%d', MT::Util::epoch2ts(undef, $pd_data->{support_expired_on}), undef, 'en', 0);
        $param->{download_expired_on}   = MT::Util::format_ts('%Y-%m-%d', MT::Util::epoch2ts(undef, $pd_data->{download_expired_on}), undef, 'en', 0);
        $param->{is_valid_support}      = $pd_data->{is_valid_support};
        $param->{is_valid_download}     = $pd_data->{is_valid_download};
        $param->{autorenew}             = $pd_data->{autorenew};
        $param->{registered}            = $pd_data->{registered};
    }
    $param->{six_apart_user_site_url} = MT->config->SixApartUserSiteURL;
    $param->{saved} = 1 if $app->param('saved');
    $app->build_page($plugin->load_tmpl('edit_license_verification.tmpl'), $param);
}

sub save {
    my ($app, $param) = @_;

    $app->validate_magic or return;
    return $app->permission_denied()
        if !$app->user->is_superuser;

    my $license_key   = $app->param('license_key');
    my $said          = $app->param('said'); 
    my $plugin        = $app->component('licenseverification');

    if (!$said) {
        return _forward_and_error($app, 'SAID is required.');
    }

    if (!$license_key) {
        return _forward_and_error($app, 'License key is required.');
    }

    my $ua    = MT->new_ua;
    my $url   = MT->config->LicenseVerificationStoreURL;
    my $token = eval { _get_token($ua, $url); };
    if (my $error = $@) {
        if ($error =~ m/^(.+?)( at .+? line \d+)(.*)$/s) {
            $error = $1;
        }
        return _forward_and_error($app, $error, 1);
    }

    my $pd      = MT::PluginData->get_by_key({ plugin => $plugin->id, key => $plugin->id, });
    my $pd_data = (ref $pd->data eq 'HASH') ? $pd->data : {};
    if (defined $pd_data->{key} && defined $pd_data->{said} &&
        (($license_key ne $pd_data->{key}) || ($said ne $pd_data->{said}))) {
        # Delete saved entry
        my %data = (
            key  => $pd_data->{key},
            said => $pd_data->{said},
        );
        if ($pd_data->{verified}) {
            $data{verified} = $pd_data->{verified};
        }
        my $request = HTTP::Request::Common::request_type_with_data('DELETE', "$url/license/verify", \%data);
        $request->header('x-token' => $token);
        my $response = $ua->request($request);
        if ($MT::DebugMode) {
            _debug($response);
        }
        unless ($response->is_success) {
            return _forward_and_error($app, 'An error occurred.', 1);
        }
        $pd->remove;
        $pd = MT::PluginData->get_by_key({ plugin => $plugin->id, key => $plugin->id, });
        $pd_data = {};
    }
    my %data = (
        key  => $license_key,
        said => $said,
    );
    if ($pd_data->{verified}) {
        $data{verified} = $pd_data->{verified};
    }
    my $request = POST("$url/license/verify", \%data);
    $request->header('x-token' => $token);
    my $response = $ua->request($request);
    if ($MT::DebugMode) {
        _debug($response);
    }
    my $content = $response->decoded_content;
    unless ($response->is_success) {
        my $msg;
        if ($content && $response->content_type =~ m/application\/json/) {
            my $obj = MT::Util::from_json(MT::Util::Encode::decode('utf-8', $content));
            $msg = $plugin->translate($obj->{message});
        }
        return _forward_and_error($app, ['An error occurred.', $msg], 1);
    }
    my $res_obj = MT::Util::from_json(MT::Util::Encode::decode('utf-8', $content));
    if ($res_obj->{status} eq 'duplicated') {
        return _forward_and_error($app, 'Your license key is already in use');
    } elsif ($res_obj->{status} eq 'invalid') {
        return _forward_and_error($app, 'The id of your license key is wrong');
    }
    for my $key (qw/is_valid_download is_valid_support autorenew/) {
        $res_obj->{$key} = ($res_obj->{$key} ? 1 : 0);
    }
    $pd->data({
        said => $said,
        %$res_obj,
    });
    $pd->save;

    return $app->redirect($app->uri(                                                                                                        
        mode   => 'edit_license_verification',
        args   => {
            blog_id => 0,
            saved   => 1,
        },
    ));
}

sub delete {
    my ($app, $param) = @_;

    $app->validate_magic or return;
    return $app->permission_denied()
        if !$app->user->is_superuser;

    my $plugin  = $app->component('licenseverification');
    my $pd      = MT::PluginData->get_by_key({ plugin => $plugin->id, key => $plugin->id, });
    my $pd_data = (ref $pd->data eq 'HASH') ? $pd->data : {};
    if ($pd_data->{key}) {
        my $ua    = MT->new_ua;
        my $url   = MT->config->LicenseVerificationStoreURL;
        my $token = eval { _get_token($ua, $url); };
        if (my $error = $@) {
            if ($error =~ m/^(.+?)( at .+? line \d+)(.*)$/s) {
                $error = $1;
            }
            return _forward_and_error($app, $error, 1);
        }

        my %data = (
            key      => $pd_data->{key},
            said     => $pd_data->{said},
        );
        if ($pd_data->{verified}) {
            $data{verified} = $pd_data->{verified};
        }
        my $request = HTTP::Request::Common::request_type_with_data('DELETE', "$url/license/verify", \%data);
        $request->header('x-token' => $token);
        my $response = $ua->request($request);
        if ($MT::DebugMode) {
            _debug($response);
        }
        unless ($response->is_success) {
            return _forward_and_error($app, 'An error occurred.', 1);
        }

        $pd->remove;
    }

    return $app->redirect($app->uri(                                                                                                        
        mode   => 'edit_license_verification',
        args   => {
            blog_id => 0,
            saved   => 1,
        },
    ));
}

sub _get_token {
    my ($ua, $url) = @_;
    my $request = HTTP::Request->new(PUT => "$url/license/token");
    $request->header('x-token-request' => 'verify'); 
    my $response = $ua->request($request);
    if ($MT::DebugMode) {
        _debug($response);
    }
    my $content = $response->decoded_content;
    unless ($response->is_success) {
        if ($content =~ m/Can't connect to @{[$request->uri->host]}/) {
            Carp::croak('Network connection issue.');
        }
        Carp::croak('Could not verify your license key.');
    }
    return $content;
}

sub _forward_and_error {
    my ($app, $message, $logged) = @_;
    my $plugin = $app->component('licenseverification');

    my $reason;
    if (ref($message) eq 'ARRAY') {
        ($message, $reason) = @$message;
    }
    my $error_message = $plugin->translate($message);

    if ($logged) {
        MT->log({
            message  => $error_message,
            level    => MT::Log::ERROR(),
            class    => 'system',
            category => $plugin->id,
            ($reason ? (metadata => $plugin->translate($reason)) : ()),
        });
    }

    my %param = $app->param_hash;
    $app->mode('edit_license_verification');
    $param{error} = $error_message,
    return $app->forward('edit_license_verification', \%param);
}

sub _debug {
    my ($response) = @_;
    my @out = (
        $response->request->method,
        $response->request->uri,
        $response->request->content,
        $response->status_line,
        $response->decoded_content,
    );
    require MT::Util::Log;
    MT::Util::Log::init();
    MT::Util::Log->debug('Store Request=' . join(' ', @out));
}

1;
__END__
