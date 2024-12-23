# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package CustomFields::DataAPI;

use strict;
use warnings;

use MT::Util ();
use CustomFields::Util qw(get_meta);

our %custom_fields_cache;

sub updatable_fields {
    ['customFields'];
}

sub fields {
    [   {   name        => 'customFields',
            from_object => sub {
                my ($obj) = @_;

                my $meta = get_meta($obj);

                my @values;
                for my $f ( @{ custom_fields($obj) } ) {
                    if ( $f->type eq 'datetime' ) {
                        my $v = $meta->{ $f->basename };
                        $v
                            = $v && $v ne '00000000000000'
                            ? MT::Util::ts2iso( MT->app->blog, $v, 1 )
                            : undef;

                        if ( $v && $f->options eq 'date' ) {
                            my @split = split 'T', $v;
                            $v = $split[0] if @split;
                        }
                        elsif ( $v && $f->options eq 'time' ) {
                            my @split = split 'T', $v;
                            $v = $split[1] if @split;
                        }

                        push @values,
                            +{
                            basename => $f->basename,
                            value    => $v,
                            };
                    }
                    else {
                        push @values,
                            +{
                            basename => $f->basename,
                            value    => $meta->{ $f->basename },
                            };
                    }
                }

                \@values;
            },
            to_object => sub {
                my ( $hash, $obj ) = @_;

                my %values = ();
                for my $v ( @{ $hash->{customFields} || [] } ) {
                    $values{ $v->{basename} } = $v->{value};
                }

                for my $f ( @{ custom_fields($obj) } ) {
                    my $bn = $f->basename;

                    next unless exists $values{$bn};

                    if ( $f->type eq 'datetime' ) {
                        if ( !defined $values{$bn} or $values{$bn} eq '' ) {
                            $values{$bn} = undef;
                        }
                        elsif ( $f->options eq 'date' ) {
                            $values{$bn} .= 'T00:00:00';
                        }
                        elsif ( $f->options eq 'time' ) {
                            $values{$bn} = '1970-01-01T' . $values{$bn};
                        }
                        $values{$bn}
                            = MT::Util::iso2ts( MT->app->blog, $values{$bn} );
                    }

                    $obj->meta( 'field.' . $bn, $values{$bn} );
                }

                return;
            },
            type_to_object => sub {
                my ( $hashes, $objs ) = @_;

                # Set default value when the value is not set.
                # "to_object" is not called when "customFields" field
                # is not set, so "type_to_object" is used.
                for ( my $i = 0; $i < scalar @$hashes; $i++ ) {
                    my $hash = $hashes->[$i];
                    my $obj  = $objs->[$i];

                    # Do not set default value to existing object.
                    next if $obj->id;

                    my %values = ();
                    for my $v ( @{ $hash->{customFields} || [] } ) {
                        $values{ $v->{basename} } = $v->{value};
                    }

                    for my $f ( @{ custom_fields($obj) } ) {
                        my $bn = $f->basename;
                        if (  !exists $values{$bn}
                            && defined $f->default
                            && $f->type ne 'datetime' )
                        {
                            $obj->meta( 'field.' . $bn, $f->default );
                        }
                    }
                }
            },
            schema => {
                type => 'array',
                items => {
                    type => 'object',
                    properties => {
                        basename => { type => 'string' },
                        value    => { type => 'string' },
                    },
                },
            },
        },
    ];
}

sub fields_for_user {
    my $field = fields()->[0];
    [   {   name        => $field->{name},
            from_object => sub {
                my ($obj) = @_;
                my $app  = MT->instance or return;
                my $user = $app->user   or return;

                return if !( $user->is_superuser || $user->id == $obj->id );

                $field->{from_object}->(@_);
            },
            to_object => $field->{to_object},
            schema => {
                type => 'array',
                items => {
                    type => 'object',
                    properties => {
                        basename => { type => 'string' },
                        value    => { type => 'string' },
                    },
                },
            },
        },
    ];
}

sub custom_fields {
    my ($obj) = @_;
    my $obj_type = $obj->class_type || $obj->datasource;
    my $blog_id;
    if ( $obj->can('blog_id') ) {
        $blog_id = $obj->blog_id;
    }
    elsif ( eval { $obj->isa('MT::Blog') } ) {
        $blog_id = $obj->id;
    }
    $blog_id ||= 0;

    my $app                 = MT->instance;
    my $custom_fields_cache = $app->request('custom_fields_cache');
    if ( !$custom_fields_cache ) {
        $app->request( 'custom_fields_cache', $custom_fields_cache = {} );
        CustomFields::Util::load_meta_fields();
    }

    $custom_fields_cache->{$obj_type} ||= {};
    if ( !$custom_fields_cache->{$obj_type}{$blog_id} ) {
        my $c = MT->component('commercial');

        $custom_fields_cache->{$obj_type}{$blog_id} = [
            grep {
                $_->obj_type eq $obj_type
                    && ( $_->blog_id == $blog_id || $_->blog_id == 0 )
            } @{ $c->{customfields} }
        ];
    }

    $custom_fields_cache->{$obj_type}{$blog_id};
}

1;
