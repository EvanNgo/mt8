# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments::DataAPI::Resource::Entry;

use strict;
use warnings;

use MT::DataAPI::Resource::Util;

sub updatable_fields {
    [qw(allowComments)];
}

sub fields {
    [   {   name                => 'allowComments',
            alias               => 'allow_comments',
            from_object_default => 0,
            type                => 'MT::DataAPI::Resource::DataType::Boolean',
        },
        {
            name  => 'commentCount',
            alias => 'comment_count',
            type  => 'MT::DataAPI::Resource::DataType::Integer',
        },
        {   name        => 'comments',
            from_object => sub {
                my ($obj) = @_;
                my $app = MT->instance;
                my $max
                    = MT::DataAPI::Resource::Util::int_param( $app,
                    'maxComments' )
                    or return [];
                my $user = $app->user;

                my $terms = undef;
                if ( !$user->is_superuser ) {
                    my $perm = $app->model('permission')->load(
                        {   author_id => $user->id,
                            blog_id   => $obj->blog_id,
                        },
                    );
                    if (!$perm
                        || !(
                            $perm->can_do('view_all_comments')
                            || (   $perm->can_do('view_own_entry_comment')
                                && $obj->author_id == $user->id )
                        )
                        )
                    {
                        require MT::Comment;
                        $terms = {
                            visible     => 1,
                            junk_status => MT::Comment::NOT_JUNK(),
                        };
                    }
                }

                my $args = {
                    sort      => 'id',
                    direction => 'ascend',
                };
                my ( @comments, @children );
                for my $c ( @{ $obj->comments( $terms, $args ) || [] } ) {
                    $c->parent_id
                        ? push( @children, $c )
                        : push( @comments, [ $c->id, $c->parent_id, $c ] );
                }
                for my $c (@children) {
                    my $parent_id = $c->parent_id;
                    my $i         = 0;
                    my $found     = 0;
                    for ( ; $i < scalar(@comments); $i++ ) {
                        if ( !$found ) {
                            if ( $comments[$i][0] == $parent_id ) {
                                $found = 1;
                            }
                        }
                        elsif ( $comments[$i][1] != $parent_id ) {
                            last;
                        }
                    }
                    splice @comments, $i, 0, [ $c->id, $c->parent_id, $c ];
                }
                @comments = map { $_->[2] } @comments;

                MT::DataAPI::Resource->from_object(
                    [     @comments > $max
                        ? @comments[ 0 .. $max - 1 ]
                        : @comments
                    ]
                );
            },
            schema => {
                type  => 'array',
                items => { '$ref' => '#/components/schemas/comment' },
            },
        },
    ];
}

1;
