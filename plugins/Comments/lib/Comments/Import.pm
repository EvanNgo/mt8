# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments::Import;

use strict;
use warnings;
use MT::Util qw( encode_html );

sub _comment_to_import {
    my ( $piece, $blog_id ) = @_;
    ## Comments are: AUTHOR, EMAIL, URL, IP, DATE (in any order),
    ## then body
    my $comment = MT::Comment->new
        or die( "Couldn't construct MT::Comment " . MT::Comment->errstr );
    $comment->blog_id($blog_id);
    $comment->approve;
    my @lines = split /\r?\n/, $piece;
    my ( $i, $body_idx ) = (0) x 2;
COMMENT:
    for my $line (@lines) {
        $line =~ s!^\s*!!;
        my ( $key, $val ) = split /\s*:\s*/, $line, 2;
        if ( $key eq 'AUTHOR' ) {
            $comment->author($val);
        }
        elsif ( $key eq 'EMAIL' ) {
            $comment->email($val);
        }
        elsif ( $key eq 'URL' ) {
            $comment->url($val);
        }
        elsif ( $key eq 'IP' ) {
            $comment->ip($val);
        }
        elsif ( $key eq 'DATE' ) {
            my $date = MT::ImportExport->_convert_date($val)
                or next;
            $comment->created_on($date);
        }
        else {
            ## Now we have reached the body of the comment;
            ## everything from here until the end of the
            ## array is body.
            $body_idx = $i;
            last COMMENT;
        }
        $i++;
    }
    $comment->text( join "\n", @lines[ $body_idx .. $#lines ] );
    return $comment;
}

sub _save_comments {
    my ( $cb, $entry, $comments ) = @_;

    for my $comment (@$comments) {
        $comment->entry_id( $entry->id );
        $cb->(
            MT->translate(
                "Creating new comment (from '[_1]')...",
                encode_html( $comment->author )
            )
        );
        if ( $comment->save ) {
            $cb->( MT->translate( "ok (ID [_1])", $comment->id ) . "\n" );
        }
        else {
            $cb->( MT->translate("failed") . "\n" );
            return MT::ImportExport->error(
                MT->translate(
                    "Saving comment failed: [_1]",
                    $comment->errstr
                )
            );
        }
    }

}

1;
