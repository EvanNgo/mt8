# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$
package Comments::Tags::Userpic;

use strict;
use warnings;

=head2 CommenterUserpic

This template tag returns a complete HTML C<img> tag for the current
commenter's userpic. For example:

    <img src="http://yourblog.com/userpics/1.jpg" width="100" height="100" />

B<Example:>

    <h2>Recent Commenters</h2>
    <mt:Entries recently_commented_on="10">
        <div class="userpic" style="float: left; padding: 5px;"><$mt:CommenterUserpic$></div>
    </mt:Entries>

=for tags comments

=cut

sub _hdlr_commenter_userpic {
    my ($ctx) = @_;
    my $c = $ctx->stash('comment')
        or return $ctx->_no_comment_error();
    my $cmntr = $ctx->stash('commenter') or return '';
    return $cmntr->userpic_html() || '';
}

###########################################################################

=head2 CommenterUserpicURL

This template tag returns the URL to the image of the current
commenter's userpic.

B<Example:>

    <img src="<$mt:CommenterUserpicURL$>" />

=for tags comments

=cut

sub _hdlr_commenter_userpic_url {
    my ($ctx) = @_;
    my $cmntr = $ctx->stash('commenter') or return '';
    return $cmntr->userpic_url() || '';
}

###########################################################################

=head2 CommenterUserpicAsset

This template tag is a container tag that puts the current commenter's
userpic asset in context. Because userpics are stored as assets within
Movable Type, this allows you to utilize all of the asset-related
template tags when displaying a user's userpic.

B<Example:>

     <mt:CommenterUserpicAsset>
        <img src="<$mt:AssetThumbnailURL width="20" height="20"$>"
            width="20" height="20"  />
     </mt:CommenterUserpicAsset>

=for tags comments, assets

=cut

sub _hdlr_commenter_userpic_asset {
    my ( $ctx, $args, $cond ) = @_;
    my $c = $ctx->stash('comment')
        or return $ctx->_no_comment_error();
    my $cmntr = $ctx->stash('commenter');

    # undef means commenter has no commenter_id
    # need default userpic asset? do nothing now.
    return '' unless $cmntr;

    my $asset = $cmntr->userpic or return '';

    my $tok     = $ctx->stash('tokens');
    my $builder = $ctx->stash('builder');

    local $ctx->{__stash}{asset} = $asset;
    $builder->build( $ctx, $tok, {%$cond} );
}

1;
