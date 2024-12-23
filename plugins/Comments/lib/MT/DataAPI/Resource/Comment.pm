# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package MT::DataAPI::Resource::Comment;

use strict;
use warnings;

use MT::DataAPI::Resource::Common;

sub updatable_fields {
    [   qw(
            body
            parent
            status
            )
    ];
}

sub fields {
    [   {   name             => 'author',
            bulk_from_object => sub {
                my ( $objs, $hashs ) = @_;

                my $app          = MT->instance;
                my $user         = $app->user;
                my $is_superuser = $user && $user->is_superuser;

                my @commenter_ids = grep {$_} map { $_->commenter_id } @$objs;
                my %authors = ();
                my @authors
                    = @commenter_ids
                    ? MT->model('author')->load( { id => \@commenter_ids, } )
                    : ();
                $authors{ $_->id } = $_ for @authors;

                my $size = scalar(@$objs);
                for ( my $i = 0; $i < $size; $i++ ) {
                    my $c = $objs->[$i];
                    my $a = $authors{ $c->commenter_id || 0 };
                    if ($a) {
                        $hashs->[$i]{author}
                            = MT::DataAPI::Resource->from_object( $a,
                            [qw(id displayName userpicUrl)] );
                    }
                    else {
                        $hashs->[$i]{author} = {
                            ( $is_superuser ? ( id => undef ) : () ),
                            displayName => $c->author,
                            userpicUrl  => undef,
                        };
                    }
                }
            },
            schema => {
                type => 'object',
                description => 'The commenter of this comment.',
                properties => {
                    id => {
                        type => 'integer',
                        description => 'The ID of this commenter. If commenter is not a registerd user, this field is empty.',
                    },
                    displayName => {
                        type => 'string',
                        description => 'The display name of this commenter.',
                    },
                    userpicUrl => {
                        type => 'string',
                        description => "The URL of this commenter's userpic. The userpic will be made by UserpicThumbnailSize and UserpicAllowRect settings. If a commenter is not a registered user or a commenter does not set own userpic, will be returned empty string.",
                    },
                },
            },
        },
        {   name        => 'entry',
            from_object => sub {
                my ($obj) = @_;
                +{ id => $obj->entry_id, };
            },
            schema => {
                type => 'object',
                description => 'The container entry of this comment.',
                properties => {
                    id => {
                        type => 'integer',
                        description => 'The ID of the entry that contains this comment.',
                    },
                },
            },
        },
        $MT::DataAPI::Resource::Common::fields{blog},
        {   name => 'id',
            type => 'MT::DataAPI::Resource::DataType::Integer',
        },
        {   name  => 'date',
            alias => 'created_on',
            type  => 'MT::DataAPI::Resource::DataType::ISO8601',
        },
        {   name        => 'body',
            alias       => 'text',
            from_object => sub {
                my ($obj) = @_;
                my $text;

                if ( $obj->blog->allow_comment_html ) {
                    $text = $obj->text;
                }
                else {
                    require MT::Util;
                    $text = MT::Util::remove_html( $obj->text );
                }

                sanitize_text( $obj, $text );
            },
        },
        {   name        => 'link',
            from_object => sub {
                my ($obj) = @_;
                $obj->permalink;
            },
        },
        {   name  => 'parent',
            alias => 'parent_id',
            type  => 'MT::DataAPI::Resource::DataType::Integer',
        },
        $MT::DataAPI::Resource::Common::fields{status},
        {   name             => 'updatable',
            type             => 'MT::DataAPI::Resource::DataType::Boolean',
            bulk_from_object => sub {
                my ( $objs, $hashs ) = @_;
                my $app  = MT->instance;
                my $user = $app->user;

                if ( $user && $user->is_superuser ) {
                    $_->{updatable} = 1 for @$hashs;
                    return;
                }

                my %blog_perms = ();
                my %entries    = ();
                my @entry_ids  = grep {$_} map { $_->entry_id } @$objs;
                my @entries
                    = @entry_ids
                    ? MT->model('entry')->load( { id => \@entry_ids, } )
                    : ();
                $entries{ $_->id } = $_ for @entries;

                for ( my $i = 0; $i < scalar @$objs; $i++ ) {
                    my $obj = $objs->[$i];

                    my ( $perms, $entry );
                    $perms = $blog_perms{ $obj->blog_id };
                    $perms ||= $user->blog_perm( $obj->blog_id ) if $user;
                    $entry = $entries{ $obj->entry_id };

                    $hashs->[$i]{updatable}
                        = $perms
                        && $entry
                        && (
                        $app->can_do(
                            'open_all_comment_edit_screen', $perms
                        )
                        || ($app->can_do(
                                'open_own_entry_comment_edit_screen', $perms )
                            && $entry
                            && ($user && $user->id == $entry->author_id)
                        )
                        );
                }
            },
        },
    ];
}

sub sanitize_text {
    my ( $obj, $text ) = @_;
    my $app  = MT->instance;
    my $user = $app->user;
    my $blog = $obj->blog;

    my $val = 1;    # default value

    # User can change 'sanitize' parameter when logging in.
    if ( $user && $user->id ) {
        $val = $app->param('sanitize');
        $val = 1 if !defined($val) || $val eq '';
    }

    if ( $val eq '1' ) {
        $val = ( $blog && $blog->sanitize_spec )
            || $app->config->GlobalSanitizeSpec;
    }

    require MT::Sanitize;
    return MT::Sanitize->sanitize( $text, $val );
}

1;

__END__

=head1 NAME

MT::DataAPI::Resource::Comment - Movable Type class for resources definitions of the MT::Comment.

=head1 AUTHOR & COPYRIGHT

Please see the I<MT> manpage for author, copyright, and license information.

=cut
