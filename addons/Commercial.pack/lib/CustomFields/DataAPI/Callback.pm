# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package CustomFields::DataAPI::Callback;
use strict;
use warnings;

use MT;
use MT::Util ();
use CustomFields::Field;

sub save_filter {
    my ( $eh, $app, $obj, $orig ) = @_;

    my $obj_type = $obj->can('class') ? $obj->class : $obj->datasource;
    my $reg = MT->registry('customfield_objects');
    return 1 unless $reg && exists $reg->{$obj_type};

    my $blog = $app->blog;
    my $blog_id = $blog ? $blog->id : undef;

    my $iter = CustomFields::Field->load_iter(
        {   $blog_id ? ( blog_id => [ $blog_id, 0 ] ) : (),
            obj_type => $obj_type,
        }
    );

    require MT::Asset;
    my $asset_types = MT::Asset->class_labels;
    while ( my $field = $iter->() ) {
        my $row        = $field->column_values();
        my $field_name = 'field.' . $row->{basename};
        if ( $row->{type} eq 'datetime' ) {
            if ( defined $obj->$field_name
                && !MT::Util::is_valid_date( $obj->$field_name ) )
            {
                return $eh->error(
                    $app->translate(
                        "Invalid date '[_1]'; dates should be real dates.",
                        $obj->$field_name,
                    )
                );
            }
        }
        elsif ( ( $row->{type} eq 'url' ) && $obj->$field_name ) {
            my $valid = 1;
            my $value = $obj->$field_name;
            $value = '' unless defined $value;
            if ( $row->{required} ) {
                $valid = MT::Util::is_url($value);
            }
            else {
                if (   ( $value ne '' )
                    && ( $value ne ( $row->{default} || '' ) ) )
                {
                    $valid = MT::Util::is_url($value);
                }
            }
            return $eh->error(
                $app->translate(
                    "Please enter valid URL for the URL field: [_1]",
                    $row->{name}
                )
            ) unless $valid;
        }

        if ( $row->{required} ) {
            return $eh->error(
                $app->translate(
                    "Please enter some value for required '[_1]' field.",
                    $row->{name}
                )
                )
                if (
                (      $row->{type} eq 'checkbox'
                    || $row->{type} eq 'select'
                    || ( $row->{type} eq 'radio' )
                )
                && !defined $obj->$field_name
                )
                || (
                (      $row->{type} ne 'checkbox'
                    && $row->{type} ne 'select'
                    && ( $row->{type} ne 'radio' )
                )
                && ( !defined $obj->$field_name
                    || $obj->$field_name eq '' )
                );
        }

        my $type_def = $app->registry( 'customfield_types', $row->{type} )
            or next;

        # handle any special field-level validation
        if ( my $h = $type_def->{validate} ) {
            $h = MT->handler_to_coderef($h) unless ref($h);
            if ( ref $h ) {
                my $value = $obj->$field_name;
                $app->error(undef);
                $value = $h->($value);
                if ( my $err = $app->errstr ) {
                    return $eh->error($err);
                }
                else {
                    $obj->$field_name($value);
                }
            }
        }

        # Validate the option of drop down menu and radio buttons.
        if (   $type_def->{options_delimiter}
            && defined $obj->$field_name
            && $obj->$field_name ne '' )
        {
            my $expr
                = '\s*' . quotemeta( $type_def->{options_delimiter} ) . '\s*';
            my @options = split /$expr/, $field->options;
            my $flag;
            foreach my $option (@options) {
                my $label = $option;
                if ( $option =~ m/=/ ) {
                    ( $option, $label ) = split /\s*=\s*/, $option, 2;
                }
                if ( $option eq $obj->$field_name ) {
                    $flag = 1;
                    last;
                }
            }
            if ( !$flag ) {
                return $eh->error(
                    $app->translate(
                        'Please enter valid option for the [_1] field: [_2]',
                        $type_def->{label},
                        $row->{name},
                    )
                );
            }
        }
    }

    1;
}

1;

