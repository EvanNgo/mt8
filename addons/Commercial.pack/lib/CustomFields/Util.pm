# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

# Original Copyright (c) 2005-2007 Arvind Satyanarayan

package CustomFields::Util;

use strict;
use warnings;
use Exporter;
@CustomFields::Util::ISA = qw( Exporter );
use vars qw( @EXPORT_OK );
@EXPORT_OK = qw( get_meta save_meta field_loop _get_tmpl _get_html
    load_meta_to_cache );
use MT::Util qw( encode_html format_ts );

sub load_meta_fields {
    my $iter = eval {
        require MT::Object;
        my $driver = MT::Object->driver;
        require CustomFields::Field;
        CustomFields::Field->load_iter;
    };
    return unless $iter;

    my ( @fields, %meta );
    while ( my $field = $iter->() ) {
        push @fields, $field;

        # install meta property
        $meta{ $field->obj_type }{ 'field.' . $field->basename }
            = $field->type;
    }

    my $cmpnt = MT->component('commercial');
    $cmpnt->{customfields} = \@fields;

    if (%meta) {
        my $types = MT->registry("customfield_types");
        foreach my $type ( keys %meta ) {
            my $ppkg = MT->model($type);
            next unless $ppkg;
            my $fields = $meta{$type};
            foreach my $field ( keys %$fields ) {
                my $cf_type = $types->{ $fields->{$field} };
                if ($cf_type) {
                    $fields->{$field} = $cf_type->{column_def} || 'vblob';
                }
                else {

                    # this type is no longer supported; so fail to install it
                    delete $fields->{$field};
                }
            }
            $ppkg->install_meta( { column_defs => $meta{$type} } );
        }
    }
}

sub load_customfield_tags {

    # Define the known tags.
    my $tags = {
        block => {
            'App:Fields' =>
                '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_app_fields',
            CustomFieldAsset =>
                '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_asset',
            'CustomFieldIsRequired?' =>
                '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_is_required',
        },
        function => {
            CustomFieldBasename =>
                '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_basename',
            CustomFieldName =>
                '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_name',
            CustomFieldDescription =>
                '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_description',
            CustomFieldValue =>
                '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_value',
            CustomFieldHTML =>
                '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_html',
        },
        help_url =>
            'https://www.movabletype.org/documentation/appendices/tags/%t.html',
    };

    for my $type (
        qw( Entry Page Category Folder Author Comment File Video Image Audio Blog Website Template  )
        )
    {
        $tags->{block}->{"${type}CustomFields"}
            = '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfields';

        $tags->{function}->{"${type}CustomFieldName"}
            = '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_name';
        $tags->{function}->{"${type}CustomFieldDescription"}
            = '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_description';
        $tags->{function}->{"${type}CustomFieldValue"}
            = '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_value';
    }

    $tags->{function}{EntryPostType} = sub {
        return
               $_[0]->stash('entry')->meta('field.post_type')
            || $_[0]->stash('entry')->class
            || q{entry};
    };

    return $tags;
}

sub install_field_tags {
    my $app = shift;

    # Add in the dynamically defined tags.
    my $cmpnt = MT->component('commercial');
    my $tags  = $cmpnt->registry('tags');
    MT->app->{__tag_handlers} = undef;
    load_meta_fields() unless $cmpnt->{customfields};
    my $fields = $cmpnt->{customfields};

    # Make asset type list
    require MT::Asset;
    my $asset_types = MT::Asset->class_labels;
    my %assets;
    foreach my $asset_type ( keys %$asset_types ) {
        $asset_type =~ s/(?:.*)\.(.*)/$1/;
        $assets{ ( lc $asset_type ) } = 1;
    }

    if ( $fields && @$fields ) {
        my $handler
            = MT->handler_to_coderef(
            '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_value_by_tag'
            );
        my $handler_asset
            = MT->handler_to_coderef(
            '$Commercial::CustomFields::Template::ContextHandlers::_hdlr_customfield_asset_by_tag'
            );
    FIELD: for my $field (@$fields) {
            my $tag = $field->tag
                or next FIELD;
            $tag = lc $tag;

            # We may be redefining these tags, but they're just string
            # references anyway.
            $tags->{function}->{$tag} = sub {
                local $_[0]->{__stash}{tag}   = $tag;
                local $_[0]->{__stash}{field} = $field;
                $handler->(@_);
            };
            if ( $assets{ ( lc $field->type ) } ) {
                $tags->{block}->{ $tag . 'asset' } = $handler_asset;
            }
        }
    }
    return 1;
}

sub field_loop {
    my $app         = MT->instance;
    my (%param)     = @_;
    my $blog_id     = $param{blog_id} || 0;
    my $obj_type    = $param{object_type};
    my $id          = $param{object_id};
    my $is_cms      = $param{is_cms} || 0;
    my $label_class = $param{label_class} || undef;
    my $params      = $param{params} || {};
    my $pre_loaded  = delete( $params->{__fields_pre_loaded__} ) || 0;

    return '' if !$obj_type;
    my $obj_class = MT->model($obj_type);

    my ( $obj, $meta_data, @pre_sort, %markers );

    # TODO: does this really need to be not-reedit only?
    if (   $id
        && !$app->param('reedit')
        && !$pre_loaded )
    {
        $obj       = $obj_class->load($id);
        $meta_data = get_meta($obj);
    }

    my %date_fields;
    for my $form_field ( $app->multi_param() ) {
        if ( $form_field =~ m/^([td]_)?customfield_(.*?)$/ ) {
            my ( $td, $field_name ) = ( $1, $2 );
            if ($td) {
                $date_fields{$field_name} = 1;
            }
            else {
                if ( $form_field =~ m/^customfield_(.*?)_cb_beacon$/ ) {
                    $field_name = $1;
                    if ( $app->param($form_field)
                        && !$app->param( 'customfield_' . $field_name ) )
                    {
                        $meta_data->{$field_name} = 0;
                    }
                }
                else {
                    $meta_data->{$field_name} = $app->param($form_field);
                }
            }
        }
    }
    for my $field_name ( keys %date_fields ) {
        my $date = '';
        my $time = '';
        if ( $app->param('mobile_view') ) {
            my $year  = $app->param("dy_customfield_$field_name");
            my $month = $app->param("dm_customfield_$field_name");
            my $day   = $app->param("dd_customfield_$field_name");
            if ( $year || $month || $day ) {
                $date = join '-', $year, $month, $day;
            }
            my $hour   = $app->param("th_customfield_$field_name");
            my $minute = $app->param("tm_customfield_$field_name");
            my $second = $app->param("ts_customfield_$field_name");
            if ( $hour || $minute || $second ) {
                $time = join ':', $hour, $minute, $second;
            }
        }
        else {
            $date = $app->param("d_customfield_$field_name");
            $time = $app->param("t_customfield_$field_name");
        }
        $meta_data->{$field_name} = {
            'date' => $date,
            'time' => $time,
        };
    }

    my $terms = {
        obj_type => $obj_type,
        $blog_id ? ( blog_id => [ $blog_id, 0 ] ) : ()
    };

    my %show_field_map = ();
    if ( $obj && $obj->isa('MT::Entry') ) {
        my $cats = $obj->categories;
        foreach my $cat (@$cats) {
            my $fields = $cat->show_fields;
            next unless defined $fields && $fields ne '';
            $show_field_map{$_} = 1 foreach split /,/, $fields;
        }
    }

    my $validate_magic = do {
        local $app->{login_again};
        $app->validate_magic;
    };

    require CustomFields::Field;
    my $iter = CustomFields::Field->load_iter($terms);
    while ( my $field = $iter->() ) {
        my ( $id, $type, $basename )
            = ( $field->id, $field->type, $field->basename );
        my $type_obj = MT->registry( 'customfield_types', $type );

        my $row = $field->get_values();
        $row->{field_label} = $row->{name};
        $row->{blog_id} ||=
            ( $obj && $obj_class->has_column('blog_id') )
            ? $obj->blog_id
            : 0;

        if ($pre_loaded) {
            $row->{value} = $params->{ 'field.' . $field->basename };
        }
        elsif ( $meta_data && defined( $meta_data->{$basename} ) ) {
            if (  !$validate_magic
                && $app->param("customfield_$basename")
                && ( grep { $_ eq $field->type }
                    qw( file audio image video ) )
                && !( $app->param("_recover") ) )
            {
                # A value of asset must be html-encoded
                $row->{value} = encode_html( $meta_data->{$basename} );
            }
            else {
                $row->{value} = $meta_data->{$basename};
            }
        }
        else {
            $row->{value} = $field->default;
        }

      # If an options_delimiter is present, we need to populate an option_loop
        if ( $type_obj->{options_delimiter} ) {
            my @option_loop;
            my $expr
                = '\s*' . quotemeta( $type_obj->{options_delimiter} ) . '\s*';
            my @options = split /$expr/, $field->options;
            foreach my $option (@options) {
                my $label = $option;
                if ( $option =~ m/=/ ) {
                    ( $option, $label ) = split /\s*=\s*/, $option, 2;
                }
                my $option_row = { option => $option, label => $label };
                $option_row->{is_selected}
                    = defined $row->{value}
                    ? ( $row->{value} eq $option )
                    : 0;
                push @option_loop, $option_row;
            }
            $row->{option_loop} = \@option_loop;
        }

        if (   ( $field->obj_type eq 'entry' )
            || ( $field->obj_type eq 'page' ) )
        {
            if ( exists( $show_field_map{ $field->id } )
                && $show_field_map{ $field->id } )
            {
                $row->{show_field} = 1;
            }
        }
        else {
            $row->{show_field} = 1;
        }
        $row->{show_hint} = $type ne 'checkbox' ? 1 : 0;
        $row->{content_class}
            = $type =~ m/radio|checkbox/ ? 'field-content-text' : '';

        # Now build the field_content using field_html
        $row->{field_id} = $row->{field_name} = "customfield_$basename";
        if ( $row->{type} eq 'datetime' ) {
            my $blog = $app->blog;
            my $ts   = $row->{value};
            if ($ts) {
                if ( $row->{options} eq 'datetime' ) {
                    $row->{field_value} = format_ts( "%x %X", $ts, $blog,
                        $app->user ? $app->user->preferred_language : undef );
                }
                elsif ( $row->{options} eq 'date' ) {
                    $row->{field_value} = format_ts( "%x", $ts, $blog,
                        $app->user ? $app->user->preferred_language : undef );
                }
                elsif ( $row->{options} eq 'time' ) {
                    $row->{field_value} = format_ts( "%H:%M:%S", $ts, $blog,
                        $app->user ? $app->user->preferred_language : undef );
                }
            }
            else {
                $row->{field_value} = '';
            }
        }
        else {
            $row->{field_value} = $row->{value};
        }
        $row->{is_cms} = $is_cms if $is_cms;
        $row->{label_class}
            = $label_class ? $label_class
            : (    $field->obj_type eq 'author'
                || $field->obj_type eq 'comment'
                || $field->obj_type eq 'category'
                || $field->obj_type eq 'folder' ) ? 'left-label'
            : 'top-label';
        $row->{field_html}
            = _get_html( $type, 'field_html', { %$params, %$row } );
        push @pre_sort, $row;
    }

    # Populate where the fields are in @pre_sort
    for ( my $i = 0; $i < scalar @pre_sort; $i++ ) {
        my $basename = $pre_sort[$i]->{basename};
        $markers{$basename} = $i;
    }

    return \@pre_sort;
}

sub load_meta_to_cache {
    $_[0]->meta_obj->lazy_load_objects;
}

sub get_meta {
    my $plugin = MT->component("Commercial");
    my ( $obj, $key ) = @_;

    if ( $obj->has_meta ) {
        return $key
            ? $obj->meta( 'field.' . $key )
            : $obj->meta_obj->get_collection('field');
    }
    else {
        my $blog_id = $obj->can('blog_id') ? $obj->blog_id : '';
        my $obj_type
            = $obj->can('class_type') ? $obj->class_type : $obj->datasource;
        my $id = $obj->id;

        require MT::PluginData;
        my $meta_data = MT::PluginData->get_by_key(
            { plugin => 'CustomFields', key => "${obj_type}_${id}" } );

        my $hash
            = ( ref $meta_data->data eq 'HASH' )
            ? $meta_data->data->{customfields}
            : {};

        return $key ? $hash->{$key} : $hash;
    }
}

sub save_meta {
    my $plugin = MT->component("Commercial");
    my ( $obj, $meta ) = @_;
    return 0 unless $obj;
    my $obj_type
        = $obj->can('class_type') ? $obj->class_type : $obj->datasource;

    if ( $obj->has_meta ) {
        my $updated = 0;
        foreach my $mf ( keys %$meta ) {
            if ( $obj->has_meta( 'field.' . $mf ) ) {
                $updated = 1;
                $obj->meta( 'field.' . $mf, $meta->{$mf} );
            }
        }
        if ($updated) {
            $obj->save or die $obj->errstr;
        }
    }
    else {
        my $id = $obj->id;

        require MT::PluginData;
        my $meta_data = MT::PluginData->get_by_key(
            { plugin => 'CustomFields', key => "${obj_type}_${id}" } );
        $meta_data->data( { customfields => $meta } );
        $meta_data->save or die $meta_data->errstr;
    }

    # Update asset placement
    # ONLY MT::Entry handles object - asset association in a different way
    # See BugId:102942 and the commit log of r108335
    sync_assets($obj) unless $obj->isa('MT::Entry');

    return 1;
}

# Inspired by (well really copied from!) MT::Entry::sync_assets
sub sync_assets {
    my $obj   = shift;
    my $meta  = get_meta($obj);
    my $class = MT->model( $obj->datasource );

    require MT::ObjectAsset;
    my @assets = MT::ObjectAsset->load(
        {   object_id => $obj->id,
            ( $class->has_column('blog_id') )
            ? ( blog_id => $obj->blog_id )
            : (),
            object_ds => $obj->datasource
        }
    );
    my %assets = map { $_->asset_id => $_->id } @assets;

    foreach my $basename ( keys %$meta ) {
        my $text = $meta->{$basename};
        next unless $text;

        while ( $text
            =~ m!<form[^>]*?\smt:asset-id=["'](\d+)["'][^>]*?>(.+?)</form>!gis
            )
        {
            my $id      = $1;
            my $innards = $2;

            # does asset exist?
            MT->model('asset')->exist( { id => $id } ) or next;

            # reference to an existing asset...
            if ( exists $assets{$id} ) {
                $assets{$id} = 0;
            }
            else {
                my $map = new MT::ObjectAsset;
                $map->blog_id( $obj->blog_id )
                    if $class->has_column('blog_id');
                $map->asset_id($id);
                $map->object_ds( $obj->datasource );
                $map->object_id( $obj->id );
                $map->save;
                $assets{$id} = 0;
            }
        }
    }

    if ( my @old_maps = grep { $assets{ $_->asset_id } } @assets ) {
        if ( UNIVERSAL::isa( $obj, 'MT::Entry' ) ) {
            my $text
                = ( $obj->text || '' ) . "\n" . ( $obj->text_more || '' );
            while ( $text
                =~ m!<form[^>]*?\smt:asset-id=["'](\d+)["'][^>]*?>(.+?)</form>!gis
                )
            {
                my $id      = $1;
                my $innards = $2;

                if ( exists $assets{$id} ) {
                    $assets{$id} = 0;
                }
            }
            @old_maps = grep { $assets{ $_->asset_id } } @old_maps;
        }
        my @old_ids = map { $_->id } @old_maps;
        MT::ObjectAsset->remove( { id => \@old_ids } );
    }
    return 1;
}

sub make_unique_field_basename {
    return shift->make_unique_field_basename();
}

sub _get_tmpl {
    my ( $type_obj, $tmpl_key, $obj_type ) = @_;

    my $tmpl = $type_obj->{$tmpl_key};
    if ( 'HASH' eq ref($tmpl) ) {
        $tmpl
            = ( $obj_type && defined $tmpl->{$obj_type} )
            ? $tmpl->{$obj_type}
            : $tmpl->{default};
    }
    return q() unless $tmpl;
    return $tmpl->($type_obj) if ref $tmpl eq 'CODE';
    if ( $tmpl =~ /\s/ ) {
        return $tmpl;
    }
    else {    # no spaces in $tmpl; must be a filename...
        my $plugin = MT->component("Commercial");
        my $ret = $plugin->load_tmpl($tmpl) or die $plugin->errstr;
        return $ret;
    }
}

my $image_support;

sub _get_html {
    my ( $key, $tmpl_key, $tmpl_param ) = @_;
    my $plugin = MT->component("Commercial");
    my $type_obj = MT->registry( 'customfield_types', $key );
    return q() unless $type_obj;

    my $snip_tmpl
        = _get_tmpl( $type_obj, $tmpl_key, $tmpl_param->{obj_type} );
    return q() unless $snip_tmpl;

    require MT::Template;
    my $tmpl;
    if ( ref $snip_tmpl ne 'MT::Template' ) {
        $tmpl = MT::Template->new(
            type   => 'scalarref',
            source => ref $snip_tmpl ? $snip_tmpl : \$snip_tmpl
        );
    }
    else {
        $tmpl = $snip_tmpl;
    }

    # $plugin->set_default_tmpl_params($tmpl);
    if ( my $p = $type_obj->{field_html_params} ) {
        $p = MT->handler_to_coderef($p) unless ref $p;
        $p->(@_);
    }

    $tmpl->param($tmpl_param);
    my $ctx = $tmpl->context;
    $ctx->stash( 'asset', undef );
    $tmpl->param( 'have_thumbnail', 0 );

    if ( $type_obj->{asset_type} ) {
        my $asset_html = $tmpl_param->{field_value};
        if ( defined($asset_html)
            && ( $asset_html =~ m/\smt:asset-id="(\d+)"/i ) )
        {
            my $asset_id = $1;
            if ( my $asset = MT::Asset->load($asset_id) ) {
                $ctx->stash( 'asset', $asset );

                unless ( defined $image_support ) {
                    eval { require MT::Image; MT::Image->new or die; };
                    $image_support = $@ ? 0 : 1;
                }
                $tmpl->param( 'have_thumbnail', $image_support );
            }
            else {
                $tmpl->param( value => '' );
            }
        }
    }
    my $html = $tmpl->output()
        or die $tmpl->errstr;
    $html =~ s/<\/?form[^>]*?>//gis;    # strip any enclosure form blocks
    $html = $plugin->translate_templatized($html);
    $html;
}

sub unpack_revision {
    my ( $cb, $obj, $packed ) = @_;

    foreach my $key ( keys %$packed ) {
        if ( my ($field) = $key =~ /^field\.(\w+)/ ) {
            $obj->column( $key, $packed->{$key}, { no_changed_flag => 1 } )
                if $obj->has_column($key);
        }
    }

    return 1;
}

sub gather_changed_cols {
    my ( $cb, $obj, $orig, $app ) = @_;

    return 1 unless $app;

    my $changed_cols = $obj->{changed_revisioned_cols} || [];

    # there is a changed col; no need to check something else
    return 1 if @$changed_cols;

    my $meta = get_meta($obj);

    my $obj_type;
    if ($obj->datasource eq 'entry') {
        $obj_type = $obj->class_type;
    } elsif ($obj->datasource eq 'template') {
        $obj_type = $obj->datasource;
    } else {
        return 1;    # exit function if $obj->datasource is invalid
    }

    for my $field (@{ MT->component('commercial')->{customfields} }) {

        # skip unrelated field to current object
        if ($field->blog_id && $field->blog_id != $obj->blog_id) {
            next;
        }
        if ($field->obj_type ne $obj_type) {
            next;
        }

        my $key = $field->basename;
        my $val = defined $meta->{$key} ? $meta->{$key} : '';    # new field has no data in $meta

        my $newval = $app->param('customfield_' . $key);
        if (defined $newval && $newval ne '') {
            if ($val ne $newval) {
                push @$changed_cols, "field.$key";
                $obj->{changed_revisioned_cols} = $changed_cols;
                last;    # no need to look through all the fields
            }
        } elsif ($val ne '') {

            # data was there but emptied now
            push @$changed_cols, "field.$key";
            $obj->{changed_revisioned_cols} = $changed_cols;
            last;    # no need to look through all the fields
        }
    }

    1;
}

sub clone_field {
    my ( $cb, %param ) = @_;

    my $old_blog_id = $param{old_blog_id};
    my $new_blog_id = $param{new_blog_id};
    my $callback    = $param{callback};
    my $app         = MT->instance;
    if ( !$app->param('clone_prefs_customfields') ) {
        my $counter = 0;
        my $state   = $app->translate("Cloning fields for blog:");
        $callback->( $state, "fields" );
        require CustomFields::Field;
        my $terms = { blog_id => $old_blog_id, };
        my $iter = CustomFields::Field->load_iter($terms);
        while ( my $field = $iter->() ) {
            $callback->(
                $state . " "
                    . $app->translate( "[_1] records processed...",
                    $counter ),
                'fields'
            ) if $counter && ( $counter % 100 == 0 );
            $counter++;
            my $new_field = $field->clone();
            delete $new_field->{column_values}->{id};
            delete $new_field->{changed_cols}->{id};
            $new_field->blog_id($new_blog_id);
            $new_field->save or die $new_field->errstr;

            # Custom field ID in show_fields is changed to new ID.
            require MT::Category;
            my $cat_iter = MT::Category->load_iter(
                { blog_id => $new_blog_id, class => '*' } );
            while ( my $cat = $cat_iter->() ) {
                if ( my $show_fields = $cat->show_fields ) {
                    my @show_fields = split /,/, $show_fields;
                    my $match = 0;
                    for ( my $i = 0; $i <= $#show_fields; $i++ ) {
                        if ( $show_fields[$i] == $field->id ) {
                            $show_fields[$i] = $new_field->id;
                            $match++;
                        }
                    }
                    if ($match) {
                        $cat->show_fields( join( ',', @show_fields ) );
                        $cat->save;
                    }
                }
            }
        }
        $callback->(
            $state . " "
                . $app->translate( "[_1] records processed.", $counter ),
            'fields'
        );
    }

    1;
}

1;
