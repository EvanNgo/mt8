# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments::App::CMS;

use strict;
use warnings;

sub mode_is_not_view { return MT->app->mode ne 'view' }

sub trust_commenter_condition {
    my $app = MT->app;
    return 0 if $app->mode eq 'view';
    return 1 if $app->user->is_superuser;
    return
          $app->config->SingleCommunity
        ? $app->blog
            ? 0
            : 1
        : $app->blog ? 1
        :              0;
}

sub delete_comment_condition {
    my $app = MT->app;
    return 1 if $app->user->is_superuser;

    my $blogs;
    if ( $app->blog ) {
        push @$blogs, $app->blog->id;
        push @$blogs, map { $_->id } @{ $app->blog->blogs }
            unless $app->blog->is_blog;
    }

    my $iter = MT->model('permission')->load_iter(
        {   author_id => $app->user->id,
            (   $blogs
                ? ( blog_id => $blogs )
                : ( blog_id => { not => 0 } )
            ),
        }
    );

    my $cond = 1;
    while ( my $p = $iter->() ) {
        $cond = 0, last
            if ( !$p->can_do('delete_own_entry_comment')
            && $p->can_do('delete_own_entry_unpublished_comment') );
    }

    if ( !$cond ) {
        my $count = MT->model('comment')->count(
            { visible => 1, },
            {   join => MT->model('entry')->join_on(
                    undef,
                    {   id        => \' = comment_entry_id',
                        author_id => $app->user->id,
                    }
                ),
            }
        );
        $cond = 1
            if $count == 0;
    }

    return $cond;
}

sub feedback_comment_condition {
    my $app = MT->app;
    return 1 if $app->user->is_superuser;

    my $blog = $app->blog;
    my $blog_ids
        = !$blog         ? undef
        : $blog->is_blog ? [ $blog->id ]
        :                  [ $blog->id, map { $_->id } @{ $blog->blogs } ];

    require MT::Permission;
    my $iter = MT::Permission->load_iter(
        {   author_id => $app->user->id,
            (   $blog_ids
                ? ( blog_id => $blog_ids )
                : ( blog_id => { not => 0 } )
            ),
        }
    );

    my $cond;
    while ( my $p = $iter->() ) {
        $cond = 1, last
            if $p->can_do('view_feedback');
    }
    return $cond ? 1 : 0;
}

sub content_actions {
    return +{
        empty_junk => {
            mode        => 'empty_junk',
            class       => 'icon-action',
            label       => 'Delete all Spam comments',
            icon        => 'ic_setting',
            return_args => 1,
            order       => 100,
            confirm_msg => sub {
                MT->translate(
                    'Are you sure you want to remove all comments reported as spam?'
                );
            },
            permit_action => {
                include_all => 1,
                permit_action =>
                    'delete_junk_comments,delete_all_junk_comments',
            }
        }
    };
}

1;
