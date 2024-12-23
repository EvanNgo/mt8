# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id:

package Comments::Blog;

use strict;
use warnings;

sub _clone_comments {
    my ( $callback, $old_blog_id, $new_blog_id, $extra ) = @_;

    my $entry_map   = $extra->{entry};
    my $comment_map = $extra->{comment};

    # Comments can only be cloned if entries are cloned.
    my $state = MT->translate("Cloning comments for blog...");
    $callback->( $state, "comments" );
    require MT::Comment;
    my $iter = MT::Comment->load_iter( { blog_id => $old_blog_id } );
    my $counter = 0;
    my %comment_parents;
    while ( my $comment = $iter->() ) {
        $callback->(
            $state . " "
                . MT->translate( "[_1] records processed...", $counter ),
            'comments'
        ) if $counter && ( $counter % 100 == 0 );
        $counter++;

        my $new_comment = $comment->clone();
        delete $new_comment->{column_values}->{id};
        delete $new_comment->{changed_cols}->{id};
        $new_comment->entry_id( $entry_map->{ $comment->entry_id } );
        $new_comment->blog_id($new_blog_id);
        $new_comment->save or die $new_comment->errstr;
        $comment_map->{ $comment->id } = $new_comment->id;
        if ( $comment->parent_id ) {
            $comment_parents{ $new_comment->id } = $comment->parent_id;
        }
    }
    foreach ( keys %comment_parents ) {
        my $comment = MT::Comment->load($_);
        if ($comment) {
            $comment->parent_id(
                $comment_map->{ $comment_parents{ $comment->id } } );
            $comment->save or die $comment->errstr;
        }
    }
    $callback->(
        $state . " " . MT->translate( "[_1] records processed.", $counter ),
        'comments'
    );
}

1;
