# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$
package Comments::Tags::Score;

use strict;
use warnings;
use MT::Template::Tags::Score;

=head2 CommentScore

A function tag that provides total score of the comment in context. Scores
grouped by namespace of a plugin are summed to calculate total score of a
comment.

B<Attributes:>

=over 4

=item * namespace (required)

Specify namespace for score to be calculated. Namespace is defined by each
plugin which leverages rating API.

=back

B<Example:>

    <$mt:CommentScore namespace="FiveStarRating"$>

=for tags comments, scoring

=cut

sub _hdlr_comment_score {
    return MT::Template::Tags::Score::_object_score_for( 'comment', @_ );
}

=head2 CommentScoreHigh

A function tag that provides the highest score of the comment in context.
Scorings grouped by namespace of a plugin are sorted to find the
highest score of a comment.

B<Attributes:>

=over 4

=item * namespace (required)

Specify namespace for the score to be sorted. Namespace is defined by each
plugin which leverages rating API.

=back

B<Example:>

    <$mt:CommentScoreHigh namespace="FiveStarRating"$>

=for tags comments, scoring

=cut

sub _hdlr_comment_score_high {
    return MT::Template::Tags::Score::_object_score_high( 'comment', @_ );
}

=head2 CommentScoreLow

A function tag that provides the lowest score of the comment in context.
Scorings grouped by namespace of a plugin are sorted to find the lowest score
of a comment.

B<Attributes:>

=over 4

=item * namespace (required)

Specify namespace for the score to be sorted. Namespace is defined by each
plugin which leverages rating API.

=back

B<Example:>

    <$mt:CommentScoreLow namespace="FiveStarRating"$>

=for tags comments, scoring

=cut

sub _hdlr_comment_score_low {
    return MT::Template::Tags::Score::_object_score_low( 'comment', @_ );
}

=head2 CommentScoreAvg

A function tag that provides the avarage score of the comment in context.
Scores grouped by namespace of a plugin are summed to calculate total
score of a comment, and average is calculated by dividing the total
score by the number of scorings or 'votes'.

B<Attributes:>

=over 4

=item * namespace (required)

Specify namespace for avarage score to be calculated. Namespace is defined by
each plugin which leverages rating API.

=back

B<Example:>

    <$mt:CommentScoreAvg namespace="FiveStarRating"$>

=for tags comments, scoring

=cut

sub _hdlr_comment_score_avg {
    return MT::Template::Tags::Score::_object_score_avg( 'comment', @_ );
}

=head2 CommentScoreCount

A function tag that provides the number of scorings or 'votes' made to
the comment in context. Scorings grouped by namespace of a plugin are
summed.

B<Attributes:>

=over 4

=item * namespace (required)

Specify namespace for the number of scorings to be calculated. Namespace is
defined by each plugin which leverages rating API.

=back

B<Example:>

    <$mt:CommentScoreCount namespace="FiveStarRating"$>

=for tags comments, scoring

=cut

sub _hdlr_comment_score_count {
    return MT::Template::Tags::Score::_object_score_count( 'comment', @_ );
}

=head2 CommentRank

A function tag which returns a number from 1 to 6 (by default) which
represents the rating of the comment in context in terms of total score
where '1' is used for the highest score, '6' for the lowest score.

B<Attributes:>

=over 4

=item * namespace (required)

Specify namespace for rank to be calculated. Namespace is defined by each plugin which leverages rating API.

=item * max (optional; default "6")

Allows a user to specify the upper bound of the scale.

=back

B<Example:>

    <$mt:CommentRank namespace="FiveStarRating"$>

=for tags comments, scoring

=cut

sub _hdlr_comment_rank {
    return MT::Template::Tags::Score::_object_rank(
        'comment',
        {   'join' => MT->model('comment')->join_on(
                undef, { id => \'= objectscore_object_id', visible => 1, }
            )
        },
        @_
    );
}

1;
