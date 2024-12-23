# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$
package MT::DataAPI::Endpoint::Comment;

use warnings;
use strict;

use MT::Util qw(remove_html);
use MT::DataAPI::Endpoint::Common;
use MT::DataAPI::Resource;
use MT::CMS::Comment;

sub list_openapi_spec {
    +{
        tags        => ['Comments'],
        summary     => 'Retrieve a list of comments',
        description => <<'DESCRIPTION',
Retrieve a list of comments.

Authorization is required to include unpublished comments
DESCRIPTION
        parameters => [{
                in          => 'query',
                name        => 'limit',
                schema      => { type => 'integer' },
                description => 'This is an optional parameter. Maximum number of comments to retrieve. Default is 10.',
            },
            {
                in          => 'query',
                name        => 'offset',
                schema      => { type => 'integer' },
                description => 'This is an optional parameter. 0-indexed offset. Default is 0.',
            },
            {
                in          => 'query',
                name        => 'includeIds',
                schema      => { type => 'string' },
                description => 'This is an optional parameter. The comma separated ID list of comments to include to result.',
            },
            {
                in          => 'query',
                name        => 'excludeIds',
                schema      => { type => 'string' },
                description => 'This is an optional parameter. The comma separated ID list of comments to exclude from result.',
            },
            {
                in     => 'query',
                name   => 'status',
                schema => {
                    type => 'string',
                    enum => [
                        'Approved',
                        'Pending',
                        'Spam',
                    ],
                },
                description => <<'DESCRIPTION',
This is an optional parameter. Filter by status.
#### Approved
comment_visible is 1 and comment_junk_status is 1.
#### Pending
comment_visible is 0 and comment_junk_status is 1.
#### Spam
comment_junk_status is -1.
DESCRIPTION
            },
            {
                in     => 'query',
                name   => 'entryStatus',
                schema => {
                    type => 'string',
                    enum => [
                        'Draft',
                        'Publish',
                        'Review',
                        'Future',
                        'Spam',
                    ],
                },
                description => <<'DESCRIPTION',
This is an optional parameter. Filter by parent entry's status.
#### Draft
entry_status is 1.
#### Publish
entry_status is 2.
#### Review
entry_status is 3.
#### Future
entry_status is 4.
#### Spam
entry_status is 5.
DESCRIPTION
            },
        ],
        responses => {
            200 => {
                description => 'OK',
                content     => {
                    'application/json' => {
                        schema => {
                            type       => 'object',
                            properties => {
                                totalResults => {
                                    type        => 'integer',
                                    description => 'The total number of comments found.',
                                },
                                items       => {
                                    type        => 'array',
                                    description => 'An array of Comments resource. The list will sorted from oldest to newest by comment_id and comment_parent_id.',
                                    items       => {
                                        '$ref' => '#/components/schemas/comment',
                                    },
                                },
                            },
                        },
                    },
                },
            },
            404 => {
                description => 'Not Found',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub list {
    my ( $app, $endpoint ) = @_;

    my $res = filtered_list( $app, $endpoint, 'comment' ) or return;

    +{  totalResults => $res->{count} + 0,
        items =>
            MT::DataAPI::Resource::Type::ObjectList->new( $res->{objects} ),
    };
}

sub list_for_entry_openapi_spec {
    +{
        tags        => ['Comments', 'Entries'],
        summary     => 'Retrieve a list of comments for an entry',
        description => <<'DESCRIPTION',
Retrieve a list of comments for an entry.

Authorization is required to include unpublished comments
DESCRIPTION
        parameters => [{
                in          => 'query',
                name        => 'limit',
                schema      => { type => 'integer' },
                description => 'This is an optional parameter. Maximum number of comments to retrieve. Default is 10.',
            },
            {
                in          => 'query',
                name        => 'offset',
                schema      => { type => 'integer' },
                description => 'This is an optional parameter. 0-indexed offset. Default is 0.',
            },
        ],
        responses => {
            200 => {
                description => 'OK',
                content     => {
                    'application/json' => {
                        schema => {
                            type       => 'object',
                            properties => {
                                totalResults => {
                                    type        => 'integer',
                                    description => 'The total number of comments found.',
                                },
                                items       => {
                                    type        => 'array',
                                    description => 'An array of Comments resource. The list will sorted from oldest to newest by comment_id and comment_parent_id.',
                                    items       => {
                                        '$ref' => '#/components/schemas/comment',
                                    },
                                },
                            },
                        },
                    },
                },
            },
            404 => {
                description => 'Not Found',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub list_for_page_openapi_spec {
    my $spec = __PACKAGE__->list_for_entry_openapi_spec();
    $spec->{tags}        = ['Comments', 'Pages'];
    $spec->{summary}     = 'Retrieve a list of comments for the specified page';
    $spec->{description} = <<'DESCRIPTION',
Retrieve a list of comments for a page.

Authorization is required to include unpublished comments
DESCRIPTION
        return $spec;
}

sub list_for_entry {
    my ( $app, $endpoint ) = @_;

    my ( $blog, $entry ) = context_objects(@_)
        or return;

    run_permission_filter( $app, 'data_api_view_permission_filter',
        $entry->class, $entry->id, obj_promise($entry) )
        or return;

    my $res = filtered_list( $app, $endpoint, 'comment',
        { entry_id => $entry->id } );

    +{  totalResults => $res->{count} + 0,
        items =>
            MT::DataAPI::Resource::Type::ObjectList->new( $res->{objects} ),
    };
}

sub _build_default_comment {
    my ( $app, $endpoint, $blog, $entry ) = @_;

    my $nick = $app->user->nickname || $app->translate('Registered User');
    my $orig_comment = $app->model('comment')->new;
    $orig_comment->set_values(
        {   ip           => $app->remote_ip,
            blog_id      => $blog->id,
            entry_id     => $entry->id,
            commenter_id => $app->user->id,
            author       => remove_html($nick),
            email        => remove_html( $app->user->email ),
        }
    );

    if ( $blog->publish_trusted_commenters ) {
        $orig_comment->approve;
    }
    else {
        $orig_comment->moderate;
    }

    $orig_comment;
}

sub _publish_and_send_notification {
    my ( $app, $blog, $entry, $comment ) = @_;

    MT::Util::start_background_task(
        sub {
            $app->rebuild_entry(
                Entry             => $entry->id,
                BuildDependencies => 1
                )
                or return $app->publish_error( "Publishing failed. [_1]",
                $app->errstr );
        }
    );
}

sub create_openapi_spec {
    +{
        tags        => ['Comments', 'Entries'],
        summary     => 'Create a new comment on an entry',
        description => <<'DESCRIPTION',
Create a new comment on an entry.

Authorization is required.
DESCRIPTION
        requestBody => {
            content => {
                'application/x-www-form-urlencoded' => {
                    schema => {
                        type       => 'object',
                        properties => {
                            comment => {
                                '$ref' => '#/components/schemas/comment_updatable',
                            },
                        },
                    },
                },
            },
        },
        responses => {
            200 => {
                description => 'OK',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/comment',
                        },
                    },
                },
            },
            404 => {
                description => 'Not Found',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub create_for_page_openapi_spec {
    my $spec = __PACKAGE__->create_openapi_spec();
    $spec->{tags}        = ['Comments', 'Pages'];
    $spec->{summary}     = 'Create a new comment on a page';
    $spec->{description} = <<'DESCRIPTION';
Create a new comment on a page.

Authorization is required.
DESCRIPTION
    return $spec;
}

sub create {
    my ( $app, $endpoint ) = @_;

    my ( $blog, $entry ) = context_objects(@_)
        or return;

    my $orig_comment
        = _build_default_comment( $app, $endpoint, $blog, $entry );

    my $new_comment = $app->resource_object( 'comment', $orig_comment )
        or return;

    save_object( $app, 'comment', $new_comment )
        or return;

    $app->_send_comment_notification( $new_comment, q(), $entry,
        $blog, $app->user );

    $new_comment;
}

sub create_reply_openapi_spec {
    +{
        tags        => ['Comments', 'Entries'],
        summary     => 'Reply to specified comment',
        description => <<'DESCRIPTION',
Reply to specified comment.

Authorization is required.
DESCRIPTION
        requestBody => {
            content => {
                'application/x-www-form-urlencoded' => {
                    schema => {
                        type       => 'object',
                        properties => {
                            comment => {
                                '$ref' => '#/components/schemas/comment_updatable',
                            },
                        },
                    },
                },
            },
        },
        responses => {
            200 => {
                description => 'OK',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/comment',
                        },
                    },
                },
            },
            404 => {
                description => 'Not Found',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub create_reply_for_page_openapi_spec {
    my $spec = __PACKAGE__->create_reply_openapi_spec();
    $spec->{tags} = ['Comments', 'Pages'];
    return $spec;
}

sub create_reply {
    my ( $app, $endpoint ) = @_;

    my ( $blog, $entry, $parent ) = context_objects(@_)
        or return;

    MT::CMS::Comment::can_do_reply( $app, $entry )
        or return $app->error(403);

    my $orig_comment
        = _build_default_comment( $app, $endpoint, $blog, $entry );
    $orig_comment->set_values( { parent_id => $parent->id, } );

    my $new_comment = $app->resource_object( 'comment', $orig_comment )
        or return;

    save_object( $app, 'comment', $new_comment )
        or return;

    $app->_send_comment_notification( $new_comment, q(), $entry,
        $blog, $app->user );

    $new_comment;
}

sub get_openapi_spec {
    +{
        tags        => ['Comments'],
        summary     => 'Retrieve a single comment by its ID',
        description => <<'DESCRIPTION',
Retrieve a single comment by its ID.

Authorization is required if the comment status is "unpublished". If the comment status is "published", then this method can be called without authorization.
DESCRIPTION
        responses => {
            200 => {
                description => 'OK',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/comment',
                        },
                    },
                },
            },
            404 => {
                description => 'Not Found',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub get {
    my ( $app, $endpoint ) = @_;

    my ( $blog, $comment ) = context_objects(@_)
        or return;

    run_permission_filter( $app, 'data_api_view_permission_filter',
        'comment', $comment->id, obj_promise($comment) )
        or return;

    $comment;
}

sub update_openapi_spec {
    +{
        tags        => ['Comments'],
        summary     => 'Update a comment',
        description => <<'DESCRIPTION',
Update a comment.

Authorization is required.
DESCRIPTION
        requestBody => {
            content => {
                'application/x-www-form-urlencoded' => {
                    schema => {
                        type       => 'object',
                        properties => {
                            comment => {
                                '$ref' => '#/components/schemas/comment_updatable',
                            },
                        },
                    },
                },
            },
        },
        responses => {
            200 => {
                description => 'OK',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/comment',
                        },
                    },
                },
            },
            404 => {
                description => 'Not Found',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub update {
    my ( $app, $endpoint ) = @_;

    my ( $blog, $comment ) = context_objects(@_)
        or return;
    my $new_comment = $app->resource_object( 'comment', $comment )
        or return;

    save_object( $app, 'comment', $new_comment, $comment )
        or return;

    $new_comment;
}

sub delete_openapi_spec {
    +{
        tags        => ['Comments'],
        summary     => 'Delete a comment',
        description => <<'DESCRIPTION',
Delete a comment.

Authorization is required.
DESCRIPTION
        responses => {
            200 => {
                description => 'OK',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/comment',
                        },
                    },
                },
            },
            404 => {
                description => 'Not Found',
                content     => {
                    'application/json' => {
                        schema => {
                            '$ref' => '#/components/schemas/ErrorContent',
                        },
                    },
                },
            },
        },
    };
}

sub delete {
    my ( $app, $endpoint ) = @_;

    my ( $blog, $comment ) = context_objects(@_)
        or return;

    remove_object( $app, 'comment', $comment )
        or return;

    $comment;
}

1;

__END__

=head1 NAME

MT::DataAPI::Endpoint::Comment - Movable Type class for endpoint definitions about the MT::Comment.

=head1 AUTHOR & COPYRIGHT

Please see the I<MT> manpage for author, copyright, and license information.

=cut
