# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

# Original Copyright (c) 2005-2007 Arvind Satyanarayan

package CustomFields::ImportExport;

use strict;
use warnings;
use MT::Util;
use MT::ImportExport;
use MT::Import;

sub PREFIX {'CF50_'}

sub import_cf {
    my ( $piece, $entry ) = @_;
    my $prefix = PREFIX();
    my $req    = MT::Request->instance();
    if ( $piece =~ s/^$prefix([\w-]+):\r?\n// ) {

        # multi-line text
        my $field_basename = 'field.' . lc($1);
        $entry->$field_basename($piece)
            if $entry->has_meta($field_basename);
    }
    else {
        foreach my $line ( split /\r?\n/, $piece ) {
            if ( $line =~ /^$prefix([\w-]+):\s+(.+)$/ ) {
                my $field_basename = lc($1);
                my $val            = $2;
                my $field = $req->cache( $field_basename . $entry->blog_id );
                unless ($field) {
                    $field = MT->model('field')->load(
                        {   basename => lc($1),
                            blog_id  => [ 0, $entry->blog_id ]
                        }
                    );
                    $req->cache( lc($1) . $entry->blog_id, $field )
                        if $field;
                }
                next unless $field;
                if ( $field->type eq 'datetime' ) {
                    $val = MT::ImportExport->_convert_date($val);
                }
                my $field_name = 'field.' . $field_basename;
                $entry->$field_name($val)
                    if $entry->has_meta($field_name);
            }
        }
    }

    1;
}

sub export_cf {
    my ($entry) = @_;
    my $meta    = $entry->meta;
    my $ret     = q();
    my %allowed = map { $_ => 1 }
        qw( text textarea checkbox url datetime select radio );
    my $req = MT::Request->instance();
    foreach my $key ( keys %$meta ) {
        if ( $key =~ /^field\.([\w-]+)$/ ) {
            my $field_basename = $1;
            my $field = $req->cache( $field_basename . $entry->blog_id );
            unless ($field) {
                $field = MT->model('field')->load(
                    {   basename => $field_basename,
                        blog_id  => [ 0, $entry->blog_id ],
                    }
                );
                $req->cache( $field_basename . $entry->blog_id, $field )
                    if $field;
            }
            next unless $field;
            next unless $allowed{ $field->type };
            my $value;
            if ( $field->type eq 'datetime' ) {
                $value = MT::Util::format_ts( '%m/%d/%Y %I:%M:%S %p',
                    $entry->$key, $entry->blog );
            }
            elsif ( $field->type eq 'textarea' ) {
                $ret .= $MT::ImportExport::SUB_SEP . "\n";
                $ret .= PREFIX() . uc($field_basename) . ":\n";
                $ret .= $entry->$key;
                $ret .= "\n" . $MT::ImportExport::SUB_SEP . "\n";
                next;
            }
            else {
                $value = $entry->$key;
            }
            $ret .= PREFIX() . uc($field_basename) . ': ' . $value . "\n";
        }
    }
    return $ret;
}

1;
__END__
