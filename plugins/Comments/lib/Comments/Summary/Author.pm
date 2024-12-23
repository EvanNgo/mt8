# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments::Summary::Author;

use strict;
use warnings;

###########################################################################

=head2 AuthorCommentCount

Returns number of comments written by the author specified by current context.

=for tags authors comment

=cut

sub _hdlr_author_comment_count {
    my ( $ctx, $args, $cond ) = @_;
    my $author = $ctx->stash('author')
        or return $ctx->_no_author_error('MTAuthorCommentCount');

    return $ctx->count_format( $author->summarize('comment_count'), $args );
}

1;

