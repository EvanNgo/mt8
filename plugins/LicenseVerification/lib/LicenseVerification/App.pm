# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package LicenseVerification::App;

use strict;
use warnings;

use MT::PluginData;

sub set_notification_dashboard {
    my ($cb, $messages) = @_;
    my $app     = MT->instance;
    my $plugin  = $app->component("licenseverification");
    my $pd      = MT::PluginData->get_by_key({ plugin => $plugin->id, key => $plugin->id, });
    my $pd_data = (ref $pd->data eq 'HASH') ? $pd->data : {};
    if ($pd_data->{support_expired_on}) {
        if (my $test_expired_on = MT->config->LicenseVerificationTestExpiredOn) {
            $pd_data->{support_expired_on} = MT::Util::ts2epoch(undef, $test_expired_on); # format: yyyymmddHHSSMM
        }
        my $notification_period     = 30;
        my $notification_started_on = MT::Util::offset_time($pd_data->{support_expired_on} - 3600 * 24 * $notification_period);
        if (MT::DateTime->compare(a => { value => $notification_started_on, type => 'epoch' }, b => { value => time, type => 'epoch' }) < 0) {
            my $support_expired_on = MT::Util::format_ts('%Y-%m-%d', MT::Util::epoch2ts(undef, $pd_data->{support_expired_on}), undef, 'en', 0);
            my $message            = {
                level => 'warning',
            };
            if (MT::DateTime->compare(a => { value => $pd_data->{support_expired_on}, type => 'epoch' }, b => { value => time, type => 'epoch' }) > 0) {
                $message->{text} = $plugin->translate('Your maintenance period will expire soon on [_1]', $support_expired_on);
            } else {
                $message->{text} = $plugin->translate('Your maintenance period has expired on [_1]', $support_expired_on);
            }
            push @$messages, $message;
        }
    }
}

1;
__END__
