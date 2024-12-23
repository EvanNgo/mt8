# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$
package CustomFields::Theme;
use strict;
use warnings;

sub apply {
    my ( $element, $theme, $obj_to_apply ) = @_;
    my $fields      = $element->{data};
    my $field_class = MT->model('field');
    my $blog_id     = $obj_to_apply->id;
    for my $basename ( keys %$fields ) {
        $field_class->count( { blog_id => $blog_id, basename => $basename } )
            and next;
        my $field = $fields->{$basename};
        for my $key (qw{ name description }) {
            my $val = $field->{$key};
            if ($val) {
                $field->{$key} = $theme->translate($val);
                if ( $field->{$key} eq $val ) {
                    $field->{$key} = $theme->translate_templatized($val);
                }
            }
        }
        my $obj = MT->model('field')->new;

        ## TBD: validate values!
        $obj->set_values(
            {   blog_id  => $blog_id,
                basename => $basename,
                %$field,
            }
        );
        $obj->save;
    }
    1;
}

sub info {
    my ( $element, $theme, $obj_to_apply ) = @_;
    my $fields    = $element->{data};
    my $component = MT->component('commercial');
    return sub {
        $component->translate( '[_1] custom fields', scalar keys %$fields );
        }
}

sub validator {
    my ( $element, $theme, $obj_to_apply ) = @_;
    my $fields = $element->{data};
    my %cols   = (
        basename => {
            label  => 'Basename',
            values => [ keys %$fields ],
        },
        tag => {
            label  => 'Template Tag',
            values => [ map { $_->{tag} } values %$fields ],
        },
    );
    my @blog_ids = ( 0, ( $obj_to_apply ? $obj_to_apply->id : () ) );
    my $context_phrase = sub {
        $_[0]
            ? MT->translate('a field on this blog')
            : MT->translate('a field on system wide');
    };
    for my $blog_id (@blog_ids) {
        for my $col_key ( keys %cols ) {
            my @terms;
            my $label  = $cols{$col_key}{label};
            my $values = $cols{$col_key}{values};
            for my $val (@$values) {
                push @terms, '-or' if scalar @terms;
                push @terms, { $col_key => $val, blog_id => $blog_id };
            }
            my $dupe;
            eval { $dupe = MT->model('field')->load( \@terms ); };
            if ($dupe) {
                return $element->error(
                    MT->translate(
                        'Conflict of [_1] "[_2]" with [_3]',
                        MT->translate($label),
                        $dupe->$col_key,
                        $context_phrase->($blog_id),
                    )
                );
            }
        }
    }

    if ($obj_to_apply) {
        while ( my ( $basename, $values ) = each %$fields ) {
            $values->{basename} = $basename;
            $values->{blog_id}  = $obj_to_apply->id;
            MT->model('field')
                ->validates_uniqueness_of_tag( $element, $values )
                or return;    # has error
        }
    }

    return 1;
}

sub condition {
    my ($blog) = @_;
    my $field = MT->model('field')
        ->load( { blog_id => $blog->id }, { limit => 1 } );
    return defined $field ? 1 : 0;
}

sub template {
    my $app = shift;
    my ( $blog, $saved ) = @_;

    my @fields = MT->model('field')->load( { blog_id => $blog->id, } );
    return unless scalar @fields;
    my @list;
    my %checked_ids;
    if ($saved) {
        %checked_ids
            = map { $_ => 1 } @{ $saved->{custom_fields_export_ids} };
    }
    for my $field (@fields) {
        my $type_def   = $app->registry( 'customfield_types', $field->type );
        my $class      = $app->model( $field->obj_type );
        my $type_label = $type_def->{label};
        my $obj_type_label = $class->class_label;

        push @list,
            {
            field_label  => $field->name,
            field_id     => $field->id,
            field_type   => $type_label,
            field_object => $obj_type_label,
            checked      => $saved ? $checked_ids{ $field->id } : 1,
            };
    }
    @list = sort { $a->{field_label} cmp $b->{field_label} } @list
        if @list;
    my %param = ( fields => \@list, );
    my $component = MT->component('commercial');
    $component->load_tmpl( 'export_field.tmpl', \%param );
}

sub export {
    my ( $app, $blog, $settings ) = @_;
    my @fields;
    if ( defined $settings ) {
        my $ids = $settings->{custom_fields_export_ids};
        if (@$ids) {
            @fields = MT->model('field')->load( { id => $ids, } );
        }
    }
    else {
        @fields = MT->model('field')->load( { blog_id => $blog->id, } );
    }
    return unless scalar @fields;
    my %data = map {
        $_->basename => {
            name        => $_->name,
            obj_type    => $_->obj_type,
            default     => $_->default,
            description => $_->description,
            options     => $_->options,
            tag         => $_->tag,
            type        => $_->type,
            required    => $_->required,
            }
    } @fields;
    return \%data;
}

1;
