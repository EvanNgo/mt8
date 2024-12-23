# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments::DataAPI::Resource::v2::Blog;

use strict;
use warnings;

use boolean;

sub updatable_fields {
    [
        # v2 fields

        # Compose Settings screen
        qw( allowCommentsDefault ),

        # Feedback Settings screen
        qw(
            allowComments
            moderateComments
            allowCommentHtml
            emailNewComments
            sortOrderComments
            convertParasComments
            useCommentConfirmation
            ),

        # Registration Settings screen
        qw(
            allowCommenterRegist
            allowUnregComments
            requireCommentEmails
            commenterAuthenticators
            ),
    ];
}

sub fields {
    [
        # Compose Settings screen
        {   name      => 'allowCommentsDefault',
            alias     => 'allow_comments_default',
            type      => 'MT::DataAPI::Resource::DataType::Boolean',
            condition => \&_can_view_cfg_screens,
        },

        # Feedback Settings screen
        {    # Do not use MT::DataAPI::Resource::DataType::Boolean,
                # because updating 2 columns at once.
            name        => 'allowComments',
            from_object => sub {
                my ($obj) = @_;
                if ( $obj->allow_reg_comments || $obj->allow_unreg_comments )
                {
                    return boolean::true();
                }
                else {
                    return boolean::false();
                }
            },
            to_object => sub {
                my ( $hash, $obj ) = @_;
                if ( $hash->{allowComments} ) {
                    $obj->allow_reg_comments(1);
                }
                else {
                    $obj->allow_unreg_comments(0);
                    $obj->allow_reg_comments(0);
                }
                return;
            },
            condition => \&_can_view_cfg_screens,
            schema    => {
                type => 'boolean',
            },
        },
        {    # Not boolean.
            name      => 'moderateComments',
            alias     => 'moderate_unreg_comments',
            condition => \&_can_view_cfg_screens,
        },
        {   name      => 'allowCommentHtml',
            alias     => 'allow_comment_html',
            type      => 'MT::DataAPI::Resource::DataType::Boolean',
            condition => \&_can_view_cfg_screens,
        },
        {    # Not boolean.
            name      => 'emailNewComments',
            alias     => 'email_new_comments',
            condition => \&_can_view_cfg_screens,
        },
        {   name      => 'sortOrderComments',
            alias     => 'sort_order_comments',
            condition => \&_can_view_cfg_screens,
        },
        {   name      => 'convertParasComments',
            alias     => 'convert_paras_comments',
            condition => \&_can_view_cfg_screens,
        },
        {   name      => 'useCommentConfirmation',
            alias     => 'use_comment_confirmation',
            type      => 'MT::DataAPI::Resource::DataType::Boolean',
            condition => \&_can_view_cfg_screens,
        },

        # Registration Settings screen
        {   name      => 'allowCommenterRegist',
            alias     => 'allow_commenter_regist',
            type      => 'MT::DataAPI::Resource::DataType::Boolean',
            condition => \&_can_view_cfg_screens,
        },
        {   name      => 'allowUnregComments',
            alias     => 'allow_unreg_comments',
            type      => 'MT::DataAPI::Resource::DataType::Boolean',
            condition => \&_can_view_cfg_screens,
        },
        {   name      => 'requireCommentEmails',
            alias     => 'require_comment_emails',
            type      => 'MT::DataAPI::Resource::DataType::Boolean',
            condition => \&_can_view_cfg_screens,
        },
        {   name        => 'commenterAuthenticators',
            alias       => 'commenter_authenticators',
            from_object => sub {
                my ($obj) = @_;
                my $auths = $obj->commenter_authenticators or return [];
                return [ split ',', $auths ];
            },
            to_object => sub {
                my ($hash) = @_;
                if ( ref $hash->{commenterAuthenticators} eq 'ARRAY' ) {
                    return join( ',', @{ $hash->{commenterAuthenticators} } );
                }
                else {
                    return '';
                }
            },
            condition => \&_can_view_cfg_screens,
            schema => {
                type  => 'array',
                items => { type => 'string' },
            },
        },

    ];
}

sub _can_view_cfg_screens {
    my $app = MT->instance;
    return $app->can_do('edit_config');
}

1;
