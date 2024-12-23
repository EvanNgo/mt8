# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package CustomFields::DataAPI::Endpoint::v2::Field;

use strict;
use warnings;

use MT::Util qw( dirify );
use MT::DataAPI::Endpoint::Common;
use MT::DataAPI::Resource;

sub list_openapi_spec {
    +{
        tags       => ['CustomFields'],
        summary    => 'Retrieve a list of fields in the specified site',
        parameters => [
            { '$ref' => '#/components/parameters/field_search' },
            { '$ref' => '#/components/parameters/field_searchFields' },
            { '$ref' => '#/components/parameters/field_limit' },
            { '$ref' => '#/components/parameters/field_offset' },
            { '$ref' => '#/components/parameters/field_sortBy' },
            { '$ref' => '#/components/parameters/field_sortOrder' },
            { '$ref' => '#/components/parameters/field_fields' },
        ],
        responses => {
            200 => {
                description => 'No Errors.',
                content     => {
                    'application/json' => {
                        schema => {
                            type       => 'object',
                            properties => {
                                totalResults => {
                                    type        => 'integer',
                                    description => ' The total number of fields.',
                                },
                                items => {
                                    type        => 'array',
                                    description => 'An array of fields resource.',
                                    items       => {
                                        '$ref' => '#/components/schemas/field',
                                    }
                                },
                            },
                        },
                    },
                },
            },
            404 => {
                description => 'Site not found.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub list {
    my ( $app, $endpoint ) = @_;

    # Parameter check
    my $include_shared = $app->param('includeShared');
    if ( defined $include_shared ) {
        return $app->error(
            $app->translate(
                'Invalid includeShared parameter provided: [_1]',
                $include_shared
            ),
            400
        ) unless lc $include_shared eq 'system';
    }

    my $res = filtered_list( $app, $endpoint, 'field' ) or return;

    return +{
        totalResults => ( $res->{count} || 0 ),
        items =>
            MT::DataAPI::Resource::Type::ObjectList->new( $res->{objects} ),
    };
}

sub get_openapi_spec {
    +{
        tags       => ['CustomFields'],
        summary    => 'Retrieve single field by its ID',
        parameters => [
            { '$ref' => '#/components/parameters/field_fields' },
        ],
        responses => {
            200 => {
                description => 'No Errors.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/field',
                        },
                    },
                },
            },
            404 => {
                description => 'Site or Field not found.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub get {
    my ( $app, $endpoint ) = @_;

    my ( $blog, $field ) = context_objects(@_)
        or return;

    run_permission_filter( $app, 'data_api_view_permission_filter',
        'field', $field->id, obj_promise($field) )
        or return;

    return $field;
}

sub create_openapi_spec {
    +{
        tags        => ['CustomFields'],
        summary     => 'Create a new field',
        requestBody => {
            content => {
                'application/x-www-form-urlencoded' => {
                    schema => {
                        type       => 'object',
                        properties => {
                            field => {
                                '$ref' => '#/components/schemas/field_updatable',
                            },
                        },
                    },
                },
            },
        },
        responses => {
            200 => {
                description => 'No Errors.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/field',
                        },
                    },
                },
            },
            404 => {
                description => 'Site not found.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub create {
    my ( $app, $endpoint ) = @_;

    my ($site) = context_objects(@_)
        or return;

    my $orig_field = MT->model('field')->new;
    $orig_field->set_values( { blog_id => $site->id, required => 0, } );

    my $new_field = $app->resource_object( 'field', $orig_field )
        or return;

    # Fill in basename automatically if empty.
    if ( !defined( $new_field->basename ) || $new_field->basename eq '' ) {
        $new_field->basename( $new_field->name );
    }

    # Fill in tag automatically if empty.
    if ( !defined( $new_field->tag ) || $new_field->tag eq '' ) {
        my $name     = '';
        my $obj_type = '';
        if ( defined $new_field->name ) {
            my $dirified = dirify( $new_field->name );
            $dirified =~ s/\-//g;
            $name = ucfirst( lc $dirified );
        }
        if ( defined $new_field->obj_type ) {
            $obj_type = ucfirst( lc $new_field->obj_type );
        }

        $new_field->tag("${obj_type}Data${name}");
    }

    save_object( $app, 'field', $new_field, $orig_field )
        or return;

    _update_show_in_these_categories( $app, $new_field );

    $new_field;
}

sub update_openapi_spec {
    +{
        tags        => ['CustomFields'],
        summary     => 'Update a field',
        requestBody => {
            content => {
                'application/x-www-form-urlencoded' => {
                    schema => {
                        type       => 'object',
                        properties => {
                            field => {
                                '$ref' => '#/components/schemas/field_updatable',
                            },
                        },
                    },
                },
            },
        },
        responses => {
            200 => {
                description => 'No Errors.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/field',
                        },
                    },
                },
            },
            404 => {
                description => 'Site or Field not found.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub update {
    my ( $app, $endpoint ) = @_;

    my ( $site, $orig_field ) = context_objects(@_)
        or return;

    my $new_field = $app->resource_object( 'field', $orig_field )
        or return;

    save_object( $app, 'field', $new_field, $orig_field )
        or return;

    _update_show_in_these_categories( $app, $new_field );

    $new_field;
}

sub delete_openapi_spec {
    +{
        tags      => ['CustomFields'],
        summary   => 'Delete a field',
        responses => {
            200 => {
                description => 'No Errors.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/field',
                        },
                    },
                },
            },
            404 => {
                description => 'Site or Field not found.',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub delete {
    my ( $app, $endpoint ) = @_;

    my ( $blog, $field ) = context_objects(@_)
        or return;

    run_permission_filter( $app, 'data_api_delete_permission_filter',
        'field', $field )
        or return;

    $field->remove
        or return $app->error(
        $app->translate(
            'Removing [_1] failed: [_2]', $field->class_label,
            $field->errstr
        ),
        500
        );

    $app->run_callbacks( 'data_api_post_delete.field', $app, $field );

    return $field;
}

sub _update_show_in_these_categories {
    my ( $app, $field ) = @_;

    return 1
        if !$field->blog_id
        || !( grep { $field->obj_type eq $_ } qw/ entry page / );

    my $json_field = $app->param('field');
    my $hash_field = $app->current_format->{unserialize}->($json_field);

    my @show_in_these_cats = ( $hash_field->{showInTheseCategories} );
    @show_in_these_cats = @{ $show_in_these_cats[0] }
        if ref $show_in_these_cats[0] eq 'ARRAY';
    my %show_in_these_cat_ids = map { $_->{id} => 1 }
        grep { ref $_ eq 'HASH' && $_->{id} } @show_in_these_cats;

    my $container_type = $app->model( $field->obj_type )->container_type;
    my @all_cats       = $app->model($container_type)
        ->load( { blog_id => $field->blog_id } );

    for my $cat (@all_cats) {
        my $original_fields = $cat->show_fields() || '';
        my %fields = map { $_ => 1 } split /,/, $original_fields;
        if ( $show_in_these_cat_ids{ $cat->id } ) {
            $fields{ $field->id } = 1;
        }
        else {
            delete $fields{ $field->id };
        }

        $cat->show_fields( join( ',', keys(%fields) ) );
        $cat->save
            or return $app->error(
            $app->translate(
                'Saving [_1] failed: [_2]', $cat->class_label,
                $cat->errstr
            ),
            500
            );
    }

    return 1;
}

1;

__END__

=head1 NAME

CustomFields::DataAPI::Endpoint::v2::Field - Movable Type class for endpoint definitions about the MT::Field.

=head1 AUTHOR & COPYRIGHT

Please see the I<MT> manpage for author, copyright, and license information.

=cut
