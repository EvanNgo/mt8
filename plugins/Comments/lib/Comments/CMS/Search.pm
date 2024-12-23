# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments::CMS::Search;

use strict;
use warnings;

sub search_apis {
    return +{
        comment => {
            order       => 200,
            condition   => \&condition,
            handler     => '$Comments::MT::CMS::Comment::build_comment_table',
            label       => 'Comments',
            perm_check  => \&perm_check,
            search_cols => {
                url    => sub { MT->app->translate('URL') },
                text   => sub { MT->app->translate('Comment Text') },
                email  => sub { MT->app->translate('Email Address') },
                ip     => sub { MT->app->translate('IP Address') },
                author => sub { MT->app->translate('Name') },
            },
            replace_cols       => ['text'],
            can_replace        => 1,
            can_search_by_date => 1,
            setup_terms_args   => \&setup_terms_args,
        },
    };
}

sub condition {
    my $author = MT->app->user;
    return 1 if $author->is_superuser;
    my $blog_id = MT->app->param('blog_id');

    my $cnt = MT->model('permission')->count(
        [   {   (     ($blog_id)
                    ? ( blog_id => $blog_id )
                    : ( blog_id => \'> 0' )
                ),
                author_id => $author->id,
            },
            '-and',
            [   { permissions => { like => "\%'create_post'\%" } },
                '-or',
                { permissions => { like => "\%'publish_post'\%" } },
                '-or',
                { permissions => { like => "\%'edit_all_posts'\%" } },
                '-or',
                { permissions => { like => "\%'manage_feedback'\%" } },
            ],
        ]
    );

    return ( $cnt && $cnt > 0 ) ? 1 : 0;
}

sub perm_check {
    my $author = MT->app->user;
    return 1
        if $author->permissions( $_[0]->blog_id )->can_do('manage_feedback');

    my $entry = MT->model('entry')->load( $_[0]->entry_id );
    return 1
        if $author->permissions( $entry->blog_id )
        ->can_edit_entry( $entry, $author );

    return 0;
}

sub setup_terms_args {
    my ( $terms, $args, $blog_id ) = @_;
    $args->{sort}      = 'created_on';
    $args->{direction} = 'descend';
}

1;
