# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package CustomFields::BackupRestore;
use strict;
use warnings;
use CustomFields::Util qw( get_meta );

# _update_meta
# save_meta without save-ing, to bypass object specific save method
sub _update_meta {
    my ( $obj, $meta ) = @_;

    if ( $obj->has_meta ) {
        my $updated = 0;
        foreach my $mf ( keys %$meta ) {
            if ( $obj->has_meta( 'field.' . $mf ) ) {
                $updated = 1;
                $obj->meta( 'field.' . $mf, $meta->{$mf} );
            }
        }
        if ($updated) {
            $obj->update()    # have to be an existing object
                or return $obj->error( $obj->errstr );
        }
    }
    else {
        my $plugin = MT->component("Commercial");
        my $obj_type
            = $obj->can('class_type') ? $obj->class_type : $obj->datasource;
        my $id = $obj->id;

        require MT::PluginData;
        my $meta_data = MT::PluginData->get_by_key(
            { plugin => 'CustomFields', key => "${obj_type}_${id}" } );
        $meta_data->data( { customfields => $meta } );
        $meta_data->save
            or return $meta_data->error( $meta_data->errstr );
    }

    return 1;
}

sub cb_restore_objects {
    my ( $cb, $objects, $deferred, $errors, $callback ) = @_;

    my %placements;
    my %assets;
    my %plugindata;
    for my $key ( keys %$objects ) {
        if ( $key =~ /^MT::ObjectAsset#(\d+)$/ ) {
            my $id = $1;
            next if $objects->{$key}->object_ds eq 'content_data';
            $placements{$id} = $objects->{$key};
        }
        elsif ( $key =~ /^MT::Asset#(\d+)$/ ) {
            my $old_id = $1;
            my $new_id = $objects->{$key}->id;
            $assets{$new_id} = {
                object => $objects->{$key},
                old_id => $old_id,
            };
        }
        elsif ( $key =~ /^MT::PluginData#(\d+)$/ ) {
            next unless 'CustomFields' eq $objects->{$key}->plugin;
            $plugindata{$1} = $objects->{$key};
        }
        elsif (( $key =~ /^MT::Category#(\d+)$/ )
            || ( $key =~ /^MT::Folder#(\d+)$/ ) )
        {
            my $cat = $objects->{$key};
            if ( my $show_fields = $cat->show_fields ) {
                my @old_show_fields = split /,/, $show_fields;
                my @show_fields;
                foreach my $old_field_id (@old_show_fields) {
                    if ( my $field
                        = $objects->{ 'CustomFields::Field#' . $old_field_id }
                        )
                    {
                        push @show_fields, $field->id;
                    }
                }
                if (@show_fields) {
                    $cat->show_fields( join( ',', @show_fields ) );
                    $cat->update();
                }
            }
        }
    }

    $callback->(
        MT->translate(
            "Importing custom fields data stored in MT::PluginData...")
    );
    while ( my ( $pd_old_id, $plugindata ) = each %plugindata ) {
        if ( $plugindata->key =~ /^(\w+)_(\d+)$/ ) {
            my $obj_type  = $1;
            my $obj_id    = $2;
            my $obj_class = MT->model($obj_type);
            next unless $obj_class;
            if ( $obj_type ne $obj_class->datasource ) {
                $obj_class = MT->model( $obj_class->datasource );
            }
            next unless $obj_class;
            my $object = $objects->{ $obj_class . '#' . $obj_id };
            next unless $object;
            $plugindata->key( $obj_type . '_' . $object->id );
            $plugindata->save;
        }
    }
    $callback->( MT->translate("Done.") . "\n" );

    # Generate old ID relation table.
    my %related;
    for my $placement ( values %placements ) {
        my $asset_hash = $assets{ $placement->asset_id };
        next unless $asset_hash;
        $related{ $asset_hash->{old_id} } = $asset_hash->{object};
    }

    require CustomFields::Field;
    require MT::BackupRestore;

    my %class_fields;
    my %processed;    # Remember updating object.
    my $i = 0;
    $callback->(
        MT->translate(
            "Importing asset associations found in custom fields ( [_1] ) ...",
            $i++
        ),
        'cf-restore-object-asset'
    );
    for my $placement ( values %placements ) {
        my $object_class = MT->model( $placement->object_ds );
        next unless $object_class;
        my $object = $object_class->load( $placement->object_id );
        next unless $object;

        # Update only once, because all asset data of the object
        # are updated in this loop.
        next if $processed{$object_class}{ $object->id };

        if ( $object->can('class_type')
            && ( $object->class_type ne $object->datasource ) )
        {
            $object_class = MT->model( $object->class_type );
            if ($object_class) {
                $object = bless $object, $object_class;
            }
        }

        my $class_type = $object_class->class_type
            || $object_class->datasource;
        unless ( exists $class_fields{$class_type}->{ $placement->blog_id } )
        {
            my $iter = CustomFields::Field->load_iter(
                {   blog_id  => [ $placement->blog_id, 0 ],
                    obj_type => $class_type,
                }
            );
            while ( my $field = $iter->() ) {
                my $class = MT->model( $field->type );
                next unless UNIVERSAL::isa( $class, 'MT::Asset' );

                $class_fields{ $field->obj_type }->{ $placement->blog_id }
                    = []
                    unless exists $class_fields{ $field->obj_type };

                push @{ $class_fields{ $field->obj_type }
                        ->{ $placement->blog_id } }, $field->basename;
            }
        }

        my $meta = get_meta($object);
        next unless $meta;
        $callback->(
            MT->translate(
                "Importing asset associations found in custom fields ( [_1] ) ...",
                $i++
            ),
            'cf-restore-object-asset'
        );
        for my $basename (
            @{ $class_fields{$class_type}->{ $placement->blog_id } } )
        {
            my $text = $meta->{$basename};
            next unless $text;
            $text = MT::BackupRestore::_sync_asset_id( $text, \%related );
            $meta->{$basename} = $text;
        }
        _update_meta( $object, $meta );

        # Remember updating object.
        $processed{$object_class}{ $object->id } = 1;
    }
    $callback->( MT->translate("Done.") . "\n" );

    1;
}

sub cb_restore_asset {
    my ( $cb, $asset, $callback ) = @_;

    my @placements = MT->model('objectasset')->load(
        {   asset_id  => $asset->id,
            blog_id   => $asset->blog_id,
            object_ds => { not => 'content_data' },
        }
    );

    require MT::BackupRestore;

    my $i = 0;
    $callback->(
        MT->translate(
            'Importing url of the assets associated in custom fields ( [_1] )...',
            $i++
        ),
        'cf-restore-asset-url'
    );
    for my $placement (@placements) {
        my $object_class = MT->model( $placement->object_ds );
        next unless $object_class;
        my $object = $object_class->load( $placement->object_id );
        next unless $object;
        if ( $object->can('class_type')
            && ( $object->class_type ne $object->datasource ) )
        {
            $object_class = MT->model( $object->class_type );
            if ($object_class) {
                $object = bless $object, $object_class;
            }
        }

        my $meta = get_meta($object);
        next unless $meta;
        $callback->(
            MT->translate(
                'Importing url of the assets associated in custom fields ( [_1] )...',
                $i++
            ),
            'cf-restore-asset-url'
        );
        for my $basename ( keys %$meta ) {
            my $text = $meta->{$basename};
            next unless $text;
            $text = MT::BackupRestore::_sync_asset_url( $text, $asset );
            $meta->{$basename} = $text;
        }
        _update_meta( $object, $meta );
    }
    $callback->( MT->translate("Done.") . "\n" );

    1;
}

sub cb_add_meta {
    my ( $cb, $field, $callback ) = @_;
    $field->add_meta;
    1;
}

1;
