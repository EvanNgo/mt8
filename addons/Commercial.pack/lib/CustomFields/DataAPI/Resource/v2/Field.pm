# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package CustomFields::DataAPI::Resource::v2::Field;

use strict;
use warnings;

use boolean ();

use MT::DataAPI::Resource::Common;

use CustomFields::App::CMS;

sub updatable_fields {
    [   qw(
            name
            description
            required
            options
            default
            basename
            tag
            ),
        {   name      => 'systemObject',
            condition => \&_create_new_field,
        },
        {   name      => 'type',
            condition => \&_create_new_field,
        },
    ];
}

sub _create_new_field {
    my $app = MT->instance or return;
    return $app->param('field_id') ? 0 : 1;
}

sub fields {
    [   qw(
            id
            name
            type
            basename
            options
            tag
            ),
        $MT::DataAPI::Resource::Common::fields{blog},
        {   name                => 'description',
            from_object_default => '',
        },
        {   name                => 'default',
            from_object_default => '',
        },
        {   name  => 'systemObject',
            alias => 'obj_type',
        },
        {   name                => 'required',
            from_object_default => 0,
            type                => 'MT::DataAPI::Resource::DataType::Boolean',
        },
        {   name        => 'updatable',
            from_object => sub {
                my ($obj) = @_;
                my $app = MT->instance;
                my $cb;
                return CustomFields::App::CMS::can_save( $cb, $app, $obj );
            },
            from_object_default => 0,
            type                => 'MT::DataAPI::Resource::DataType::Boolean',
        },
        {   name             => 'showInTheseCategories',
            bulk_from_object => sub {
                my ( $objs, $hashes ) = @_;
                my $app = MT->instance;

                for ( my $i = 0; $i < scalar @$objs; $i++ ) {
                    my $obj  = $objs->[$i];
                    my $hash = $hashes->[$i];

                    next
                        if !(
                           $obj->id
                        && $obj->blog_id
                        && (   $obj->obj_type eq 'entry'
                            || $obj->obj_type eq 'page' )
                        );

                    $hash->{showInTheseCategories} = [];

                    my $entry_class = $app->model( $obj->obj_type );
                    my $cat_class
                        = $app->model( $entry_class->container_type );
                    require MT::App::CMS;
                    my $cats = MT::App::CMS::_build_category_list(
                        $app,
                        blog_id => $obj->blog_id,
                        type    => $entry_class->container_type
                    );

                    foreach (@$cats) {
                        if ( grep { $_ == $obj->id }
                            @{ $_->{category_fields} } )
                        {
                            my $cats = $hash->{showInTheseCategories} ||= [];
                            push @$cats,
                                {
                                id    => $_->{category_id},
                                label => $_->{category_label}
                                };
                        }
                    }
                }
            },
            schema => {
                type  => 'array',
                items => {
                    type       => 'object',
                    properties => {
                        id    => { type => 'string' },
                        label => { type => 'string' },
                    },
                },
            },
        },
    ];
}

1;

__END__

=head1 NAME

CustomFields::DataAPI::Resource::v2::Field - Movable Type class for resources definitions of the MT::Field.

=head1 AUTHOR & COPYRIGHT

Please see the I<MT> manpage for author, copyright, and license information.

=cut
