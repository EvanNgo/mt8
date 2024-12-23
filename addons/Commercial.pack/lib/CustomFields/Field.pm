# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

# Original Copyright (c) 2005-2007 Arvind Satyanarayan

package CustomFields::Field;

use strict;
use warnings;
use base qw( MT::Object );

use MT::Util qw( dirify );

__PACKAGE__->install_properties(
    {   column_defs => {
            'id'          => 'integer not null auto_increment',
            'blog_id'     => 'integer',
            'name'        => 'string(255) not null',
            'description' => 'text',
            'obj_type'    => 'string(50) not null',
            'type'        => 'string(50) not null',
            'tag'         => 'string(255) not null',
            'default'     => 'text',
            'options'     => 'text',
            'required'    => 'boolean',
            'basename'    => 'string(255)',
        },
        indexes => {
            blog_id  => 1,
            name     => 1,
            obj_type => 1,
            type     => 1,
            basename => 1,
            blog_tag => { columns => [ 'blog_id', 'tag' ], },
        },
        primary_key => 'id',
        datasource  => 'field',
        child_of    => [ 'MT::Blog', 'MT::Website' ],
    }
);

sub class_label {
    return MT->translate("Field");
}

sub class_label_plural {
    return MT->translate("Fields");
}

sub save {
    my $field = shift;

    ## If there's no basename specified, create a unique basename.
    if ( !defined( $field->basename ) || ( $field->basename eq '' ) ) {
        my $name = $field->make_unique_field_basename();
        $field->basename($name);
    }

    my $id = $field->id;
    my $orig_basename;

    if ($id) {
        if ( $field->{changed_cols}{basename} ) {
            my $orig_obj = __PACKAGE__->load($id);
            $orig_basename = $orig_obj->basename;
        }
    }

    my $res = $field->SUPER::save(@_);
    if ($res) {
        if ( !$id ) {    # new meta field
                         # install it!
            $field->add_meta();
        }
        if ( defined $orig_basename ) {

            # update existing meta records to use new basename
            my $ppkg = MT->model( $field->obj_type );
            if ($ppkg) {
                if ( my $mpkg = $ppkg->meta_pkg ) {
                    my $driver = $mpkg->driver;
                    my $type_col
                        = $driver->dbd->db_column_name( $mpkg->datasource,
                        'type' );
                    my $dbh = $driver->r_handle;
                    if ( $field->basename ne $orig_basename ) {
                        my $basename = $field->basename;
                        $orig_basename = $orig_basename;

                        $driver->sql( 'update '
                                . $mpkg->table_name
                                . qq{ set $type_col='field.$basename'}
                                . qq{ where $type_col='field.$orig_basename'}
                        );

                        $driver->clear_cache if $driver->can('clear_cache');
                    }
                }
            }
        }
    }

    return $res;
}

sub make_unique_field_basename {
    my $field = shift;
    my %param = @_;
    my ( $base_stem, $field_id, $blog_id, $type, $check_only )
        = @param{qw( stem field_id blog_id type check_only )};

    if ( ref $field ) {
        $base_stem ||= $field->name;
        $field_id  ||= $field->id;
        $blog_id   ||= $field->blog_id;
        $type      ||= $field->type;
    }

    # Normalize the suggested basename.
    $base_stem ||= q{};
    $base_stem =~ s{ \A \s+ | \s+ \z }{}xmgs;
    $base_stem = dirify($base_stem);
    my $limit = 30;
    if ($blog_id) {
        my $blog = MT->model('blog')->load($blog_id);
        if ( $blog && $blog->basename_limit ) {
            $limit = $blog->basename_limit;
            $limit = 15 if $limit < 15;
            $limit = 250 if $limit > 250;
        }
    }
    $base_stem = substr $base_stem, 0, $limit;
    $base_stem =~ s{ _+ \z }{}xms;
    $base_stem ||= 'cf';

    my $i     = 1;
    my $base  = $base_stem;
    my $class = MT->model('field');
    my %terms
        = $field_id
        ? ( id => { op => '!=', value => $field_id } )
        : ();

    # System level fields have to be totally unique.
    if ( !$blog_id ) {
        while ( $class->count( { %terms, basename => $base } ) ) {
            return if $check_only;
            $base = $base_stem . '_' . $i++;
        }
        return $base;
    }

    # Blog level fields have to not conflict with a system field or a field
    # from a different blog with a different data type.
BASE:
    while ( my @dupes = $class->load( { %terms, basename => $base } ) ) {
    DUP_FIELD: for my $dup_field (@dupes) {
            my $conflict
                = !$dup_field->blog_id            ? 1
                : $dup_field->blog_id == $blog_id ? 1
                : $dup_field->type ne $type       ? 1
                :                                   0;
            if ($conflict) {
                return if $check_only;
                $base = $base_stem . '_' . $i++;
                next BASE;
            }
        }

        # All the duplicate fields are fields of the same type in other
        # blogs. We can use that basename too.
        last BASE;
    }

    return $base;
}

sub parents {
    my $obj = shift;
    {   blog_id => {
            class    => [ MT->model('blog'), MT->model('website') ],
            optional => 1
        },
    };
}

sub create_obj_to_backup {
    my $class = shift;
    my ( $blog_ids, $obj_to_backup, $populated, $order ) = @_;

    # system fields have to be backed up earlier
    push @$obj_to_backup, {
        $class => {
            terms => { 'blog_id' => '0' },
            args  => undef
        },
        'order' => 300,    # earlier than website
    };

    if ( defined($blog_ids) && scalar(@$blog_ids) ) {
        push @$obj_to_backup,
            {
            $class => {
                terms => { 'blog_id' => $blog_ids },
                args  => undef
            },
            'order' => $order,
            };
    }
    else {
        push @$obj_to_backup,
            {
            $class =>
                { terms => { 'blog_id' => { 'not' => '0' } }, args => undef },
            'order' => $order,
            };
    }
}

sub add_meta {
    my $field = shift;

    my $ppkg = MT->model( $field->obj_type );
    return 0 unless $ppkg;

    my $types = MT->registry("customfield_types");

    my $fields  = {};
    my $cf_type = $types->{ $field->type }
        or return 0;

    my $type = $cf_type->{column_def} || 'vblob';
    $ppkg->install_meta(
        { column_defs => { 'field.' . $field->basename => $type } } );
    return 1;
}

sub blog {
    my $self = shift;
    my $blog_id = $self->blog_id or return undef;
    MT->model('blog')->load($blog_id);
}

sub type_label {
    my $self = shift;
    my $type = $self->type
        or return '';
    my $customfield_types = MT->registry('customfield_types');
    foreach my $key ( keys %$customfield_types ) {
        next if ref $key eq 'HASH';
        if ( $key eq $type ) {
            return $customfield_types->{$key}{label};
        }
    }
    return '';
}

sub validates_uniqueness_of_tag {
    my $class = shift;
    my ( $eh, $values ) = @_;
    if ( ref $class ) {
        $values = $class->get_values;
        $class  = ref $class;
    }
    my $tag = lc $values->{tag};

    # Check for same template tag
    #
    # For system level customfields.
    # * The field of the same tag name must not exist in system and any blog.
    # For blog level customfields.
    # * The field of the same tag name must not exist in system and oneself.
    my @unique_tag_terms = [
        {   tag => $tag,
            (   $values->{blog_id} ? ( blog_id => [ $values->{blog_id}, 0 ] )
                : ()
            ),
            (   $values->{id}
                ? ( id => { op => '!=', value => $values->{id} } )
                : ()
            )
        }
    ];

    # Check for same template tag and different attributes
    #
    # The field of the same tag name (between blogs) can exist only for
    # when the following attributes are the same.
    # * basename
    # * obj_type
    # * type
    if ( $values->{blog_id} ) {
        push(
            @unique_tag_terms,
            '-or',
            [   {   tag => $tag,
                    (   $values->{blog_id}
                        ? ( blog_id =>
                                { op => '!=', value => $values->{blog_id} } )
                        : ()
                    ),
                },
                '-and',
                [   {   basename =>
                            { op => '!=', value => $values->{basename} }
                    },
                    '-or',
                    {   obj_type =>
                            { op => '!=', value => $values->{obj_type} }
                    },
                    '-or',
                    { type => { op => '!=', value => $values->{type} } },
                ],
            ]
        );
    }

    my $field = $class->load( \@unique_tag_terms );

    # unique
    return 1 unless $field;

    # not unique
    if ( $field->blog_id == 0 ) {
        return $eh->error(
            MT->translate(
                "The template tag '[_1]' is already in use in the system level",
                $values->{tag},
            )
        );
    }
    elsif ( !$values->{blog_id} ) {
        return $eh->error(
            MT->translate(
                "The template tag '[_1]' is already in use in [_2]",
                $values->{tag}, $field->blog->name
            )
        );
    }
    elsif ( $values->{blog_id} == $field->blog_id ) {
        return $eh->error(
            MT->translate(
                "The template tag '[_1]' is already in use in this site",
                $values->{tag},
            )
        );
    }
    elsif ( $values->{basename} ne $field->basename ) {
        return $eh->error(
            MT->translate(
                "The '[_1]' of the template tag '[_2]' that is already in use in [_3] is [_4].",
                MT->translate('_CF_BASENAME'),
                $values->{tag},
                $field->blog->name,
                $field->basename
            )
        );
    }
    elsif ( $values->{obj_type} ne $field->obj_type ) {
        return $eh->error(
            MT->translate(
                "The '[_1]' of the template tag '[_2]' that is already in use in [_3] is [_4].",
                MT->translate('System Object'),
                $values->{tag},
                $field->blog->name,
                MT->model( $field->obj_type )->class_label
            )
        );
    }
    elsif ( $values->{type} ne $field->type ) {
        return $eh->error(
            MT->translate(
                "The '[_1]' of the template tag '[_2]' that is already in use in [_3] is [_4].",
                MT->translate('Type'),
                $values->{tag},
                $field->blog->name,
                $field->type_label
            )
        );
    }
    else {
        return $eh->error(
            MT->translate(
                "The template tag '[_1]' is already in use.",
                $values->{tag},
            )
        );
    }
}

#hint for l10n
#trans('__CF_REQUIRED_VALUE__')

1;
