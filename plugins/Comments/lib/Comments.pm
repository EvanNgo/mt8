# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments;

use strict;
use warnings;

sub init {
    _add_comments_callbacks();
}

sub _add_comments_callbacks {
    require MT::Comment;
    MT::Comment->add_callback( 'post_save', 0, MT->component('core'),
        \&__update_comment_count );

    MT::Comment->add_callback( 'post_remove', 0, MT->component('core'),
        \&__update_comment_count );
}

sub __update_comment_count {
    my ( $cb, $comment ) = @_;
    return unless $comment->entry_id;
    my $entry = MT::Entry->load( $comment->entry_id ) or return;
    $entry->clear_cache('comment_latest');
    my $count = MT::Comment->count(
        {   entry_id => $comment->entry_id,
            visible  => 1,
        }
    );
    return if $entry->comment_count == $count;
    $entry->comment_count($count);
    $entry->save;
}

sub _pre_load_filtered_list_commenter {
    my ( $cb, $app, $filter, $opts, $cols ) = @_;
    my $terms = $opts->{terms};
    my $args  = $opts->{args};
    $args->{joins} ||= [];
    push @{ $args->{joins} },
        MT->model('permission')->join_on(
        undef,
        [   { blog_id => 0 },
            '-and',
            { author_id => \'= author_id', },
            '-and',
            [   { permissions => { like => '%comment%' } },
                '-or',
                { restrictions => { like => '%comment%' } },
                '-or',
                [   { permissions => \'IS NULL' },
                    '-and',
                    { restrictions => \'IS NULL' },
                ],
            ],
        ],
        );
}

sub listing_screens_commenter_condition {
    return MT->config->SingleCommunity;
}

sub list_props_blog {
    return +{
        comment_count => {
            label              => 'Comments',
            filter_label       => '__COMMENT_COUNT',
            order              => 600,
            base               => '__virtual.object_count',
            count_class        => 'comment',
            count_col          => 'blog_id',
            filter_type        => 'blog_id',
            list_screen        => 'comment',
            list_permit_action => 'access_to_comment_list',
        },
    };
}

sub _comment_status_icon {
    my ($comment)               = @_;
    my $status_class            = _comment_status_class($comment);
    my $status_icon_color_class = _comment_status_icon_color_class($comment);
    my $status_icon_id          = _comment_status_icon_id($comment);
    my $static_uri              = MT->static_path;
    return qq{
        <svg role="img" class="mt-icon mt-icon--sm$status_icon_color_class">
            <title>$status_class</title>
            <use xlink:href="${static_uri}images/sprite.svg#$status_icon_id"></use>
        </svg>
    };
}

sub _comment_status_class {
    my ($comment) = @_;
    return
          $comment->is_junk      ? 'Junk'
        : $comment->is_published ? 'Approved'
        :                          'Unapproved';
}

sub _comment_status_icon_color_class {
    my ($comment) = @_;
    return
          $comment->is_junk      ? ' mt-icon--warning'
        : $comment->is_published ? ' mt-icon--success'
        :                          '';
}

sub _comment_status_icon_id {
    my ($comment) = @_;
    return
          $comment->is_junk      ? 'ic_error'
        : $comment->is_published ? 'ic_checkbox'
        :                          'ic_statusdraft';
}

sub _comment_author_html {
    my ( $prop, $obj, $app, $opts, $no_link ) = @_;
    my $name = MT::Util::remove_html( $obj->author );
    my ( $link, $status_img, $status_class, $lc_status_class,
        $auth_img, $auth_label );
    my $id     = $obj->commenter_id;
    my $static = MT->static_path;

    if ( !$id ) {
        $link = $app->uri(
            mode => 'search_replace',
            args => {
                _type       => 'comment',
                search_cols => 'author',
                is_limited  => 1,
                do_search   => 1,
                search      => $name,
                blog_id     => $app->blog ? $app->blog->id : 0,
            }
        );
        $status_img      = '';
        $status_class    = 'Anonymous';
        $lc_status_class = lc $status_class;
        my $link_title
            = MT->translate(
            'Search for other comments from anonymous commenters');
        return $no_link
            ? qq{
            <span class="commenter">
                $name
            </span>
        }
            : qq{
            <span class="commenter">
                <a href="$link" title="$link_title">$name</a>
            </span>
        };
    }

    $link = $app->uri(
        mode => 'view',
        args => {
            _type   => 'commenter',
            id      => $id,
            blog_id => $obj->blog_id,
        }
    );
    my $commenter = MT->model('author')->load($id);

    if ($commenter) {
        $name ||= $commenter->name
            || '(' . MT->translate('Registered User') . ')';
    }
    else {
        $name ||= '(' . MT->translate('__ANONYMOUS_COMMENTER') . ')';
        $link = $app->uri(
            mode => 'search_replace',
            args => {
                _type       => 'comment',
                search_cols => 'author',
                is_limited  => 1,
                do_search   => 1,
                search      => $name,
                blog_id     => $app->blog ? $app->blog->id : 0,
            }
        );
        $status_img      = '';
        $status_class    = 'Deleted';
        $lc_status_class = lc $status_class;
        my $link_title
            = MT->translate(
            'Search for other comments from this deleted commenter');
        my $optional_status = MT->translate('(Deleted)');
        return $no_link
            ? qq{
            <span class="commenter">
                $name $optional_status
            </span>
        }
            : qq{
            <span class="commenter">
                <a href="$link" title="$link_title">$name</a> $optional_status
            </span>
        };
    }

    my $status = $commenter->status;
    my $badge_type;

    if ( MT->config->SingleCommunity ) {
        if ( $commenter->type == MT::Author::AUTHOR() ) {
            $badge_type
                = $commenter->status == MT::Author::ACTIVE()   ? 'success'
                : $commenter->status == MT::Author::INACTIVE() ? 'default'
                :                                                'warning';
            $status_class
                = $commenter->status == MT::Author::ACTIVE()   ? 'Enabled'
                : $commenter->status == MT::Author::INACTIVE() ? 'Disabled'
                :                                                'Pending';
        }
        else {
            $badge_type
                = $commenter->is_trusted(0) ? 'success'
                : $commenter->is_banned(0)  ? 'default'
                :                             'info';
            $status_class
                = $commenter->is_trusted(0) ? 'Trusted'
                : $commenter->is_banned(0)  ? 'Banned'
                :                             'Authenticated';
        }
    }
    else {
        my $blog_id = $opts->{blog_id};
        $badge_type
            = $commenter->is_trusted($blog_id) ? 'success'
            : $commenter->is_banned($blog_id)  ? 'default'
            :                                    'info';
        $status_class
            = $commenter->is_trusted($blog_id) ? 'Trusted'
            : $commenter->is_banned($blog_id)  ? 'Banned'
            :                                    'Authenticated';
    }

    $lc_status_class = lc $status_class;
    my $translated_status_class = MT->translate($status_class);

    $auth_img = $static;
    if (   $commenter->auth_type eq 'MT'
        || $commenter->auth_type eq 'LDAP' )
    {
        $auth_img .= 'images/comment/mt_logo.png';
        $auth_label = 'Movable Type';
    }
    else {
        my $auth = MT->registry(
            commenter_authenticators => $commenter->auth_type );
        $auth_img .= $auth->{logo_small};
        $auth_label = $auth->{label};
        $auth_label = $auth_label->() if ref $auth_label;
    }
    my $link_title = MT->translate(
        'Edit this [_1] commenter.',
        MT->translate($status_class),
    );

    my $static_uri = MT->static_path;
    my $out        = qq{
        <span class="auth-type">
            <img alt="$auth_label" src="$auth_img" class="auth-type-icon" />
        </span>
        <span class="commenter">
    };
    if ( $app->can_do('view_commenter') ) {
        $out .=
              $no_link
            ? $name
            : qq{<a href="$link" title="$link_title">$name</a>};
    }
    else {
        $out .= $name;
    }
    $out .= qq{
        </span>
        <span class="status $lc_status_class">
            <svg role="img" class="mt-icon mt-icon--sm">
            <title>$translated_status_class</title>
            <use xlink:href="${static_uri}images/sprite.svg#ic_user"></use>
            </svg>
        </span>
        <span class="badge badge-$badge_type">$translated_status_class</span>
    };
}

sub list_props_comment {
    return +{
        comment => {
            label   => 'Comment',
            order   => 100,
            display => 'force',
            html    => sub {
                my ( $prop, $obj, $app ) = @_;
                my $text = MT::Util::remove_html( $obj->text );
                ## FIXME: Hard coded...
                my $len = 4000;
                if ( $len < length($text) ) {
                    $text = substr( $text, 0, $len );
                    $text .= '...';
                }
                elsif ( !$text ) {
                    $text = '...';
                }
                my $id   = $obj->id;
                my $link = $app->uri(
                    mode => 'view',
                    args => {
                        _type   => 'comment',
                        id      => $id,
                        blog_id => $obj->blog_id,
                    }
                );

                my $status_img = _comment_status_icon($obj);

                my $blog = $app ? $app->blog : undef;
                my $edit_str = MT->translate('Edit');
                my $reply_link;
                if ( $app->user->permissions( $obj->blog->id )
                    ->can_do('reply_comment_from_cms')
                    and $obj->is_published )
                {
                    my $return_arg = $app->uri_params(
                        mode => 'list',
                        args => {
                            _type   => 'comment',
                            blog_id => $app->blog ? $app->blog->id : 0,
                        }
                    );
                    my $reply_url = $app->uri(
                        mode => 'dialog_post_comment',
                        args => {
                            reply_to    => $id,
                            blog_id     => $obj->blog_id,
                            return_args => $return_arg,
                            magic_token => $app->current_magic,
                        },
                    );
                    my $reply_str = MT->translate('Reply');
                    $reply_link
                        = qq{<a href="$reply_url" class="reply action-link open-dialog-link mt-open-dialog" onclick="jQuery.fn.mtModal.open('$reply_url', { large: true }); return false;">$reply_str</a>};
                }

                my $lc_status_class = lc _comment_status_class($obj);

                return qq{
                    <div class="mb-3">
                      <span class="icon comment status $lc_status_class">
                        $status_img
                      </span>
                      <span class="comment-text content-text">$text</span>
                    </div>
                    <div class="item-ctrl">
                      <a href="$link" class="edit action-link">$edit_str</a>
                      $reply_link
                    </div>
                };
            },
            default_sort_order => 'descend',
        },
        author => {
            label     => 'Commenter',
            order     => 200,
            auto      => 1,
            display   => 'default',
            use_blank => 1,
            html      => \&_comment_author_html,
        },
        created_on => {
            order   => 250,
            base    => '__virtual.created_on',
            display => 'default',
        },
        ip => {
            auto      => 1,
            order     => 300,
            label     => 'IP Address',
            condition => sub { MT->config->ShowIPInformation },
        },
        blog_name => {
            base  => '__common.blog_name',
            order => 400,
        },
        entry => {
            label              => 'Entry/Page',
            base               => '__virtual.integer',
            col_class          => 'string',
            filter_editable    => 0,
            order              => 500,
            display            => 'default',
            default_sort_order => 'ascend',
            filter_tmpl        => '<mt:Var name="filter_form_hidden">',
            sort               => sub {
                my $prop = shift;
                my ( $terms, $args ) = @_;
                $args->{joins} ||= [];
                push @{ $args->{joins} },
                    MT->model('entry')->join_on(
                    undef,
                    { id => \'= comment_entry_id', },
                    {   sort      => 'title',
                        direction => $args->{direction} || 'ascend',
                    },
                    );
                $args->{sort} = [];
                return;
            },
            terms => sub {
                my ( $prop, $args, $db_terms, $db_args ) = @_;
                $db_args->{joins} ||= [];
                push @{ $db_args->{joins} },
                    MT->model('entry')->join_on(
                    undef,
                    {   id =>
                            [ '-and', $args->{value}, \'= comment_entry_id' ],
                    },
                    );
                return;
            },
            bulk_html => sub {
                my $prop = shift;
                my ( $objs, $app ) = @_;
                my %entry_ids = map { $_->entry_id => 1 } @$objs;
                my @entries
                    = MT->model('entry')
                    ->load( { id => [ keys %entry_ids ], },
                    { no_class => 1, } );
                my %entries = map { $_->id => $_ } @entries;
                my @result;
                for my $obj (@$objs) {
                    my $id    = $obj->entry_id;
                    my $entry = $entries{$id};
                    if ( !$entry ) {
                        push @result, MT->translate('Deleted');
                        next;
                    }
                    my $title_html
                        = MT::ListProperty::make_common_label_html( $entry,
                        $app, 'title', 'No title' );

                    my $static_uri = $app->static_path;
                    my $icon_title = $entry->class_label;
                    my $icon_color = $entry->is_entry ? 'success' : 'info';
                    my $icon       = qq{
                        <svg role="img" class="mt-icon--sm mt-icon--$icon_color">
                            <title>$icon_title</title>
                            <use xlink:href="${static_uri}images/sprite.svg#ic_file"></use>
                        </svg>
                    };

                    push @result, qq{
                        $icon
                        $title_html
                    };
                }
                return @result;
            },
            raw => sub {
                my ( $prop, $obj ) = @_;
                my $entry_id = $obj->entry_id;
                return $entry_id
                    ? MT->model('entry')->load($entry_id)->title
                    : '';
            },
            label_via_param => sub {
                my $prop = shift;
                my ( $app, $val ) = @_;
                my $entry = MT->model('entry')->load($val)
                    or return $prop->error(
                    MT->translate(
                        '[_1] ( id:[_2] ) does not exists.',
                        MT->translate("Entry"),
                        defined $val ? $val : ''
                    )
                    );
                my $label = MT->translate( 'Comments on [_1]: [_2]',
                    $entry->class_label, $entry->title, );
                $prop->{filter_label} = MT::Util::encode_html($label);
                $label;
            },
            args_via_param => sub {
                my $prop = shift;
                my ( $app, $val ) = @_;
                return { option => 'equal', value => $val };
            },
        },

        modified_on => {
            display => 'none',
            base    => '__virtual.modified_on',
        },
        status => {
            label   => 'Status',
            base    => '__virtual.single_select',
            col     => 'visible',
            display => 'none',
            terms   => sub {
                my $prop  = shift;
                my $value = $prop->normalized_value(@_);
                require MT::Comment;
                return $value eq 'approved'
                    ? { visible => 1, junk_status => MT::Comment::NOT_JUNK() }
                    : $value eq 'pending'
                    ? { visible => 0, junk_status => MT::Comment::NOT_JUNK() }
                    : $value eq 'junk'
                    ? { junk_status => MT::Comment::JUNK() }
                    : { junk_status => MT::Comment::NOT_JUNK() };
            },
            single_select_options => [
                {   label => MT->translate('Approved'),
                    text  => 'Approved',
                    value => 'approved',
                },
                {   label => MT->translate('Unapproved'),
                    text  => 'Pending',
                    value => 'pending',
                },
                { label => MT->translate('Not spam'), value => 'not_junk', },
                {   label => MT->translate('Reported as spam'),
                    text  => 'Spam',
                    value => 'junk',
                },
            ],
        },
        ## Hide default author_name.
        author_name  => { view => 'none', },
        commenter_id => {
            auto            => 1,
            filter_editable => 0,
            display         => 'none',
            label           => 'Commenter',
            label_via_param => sub {
                my $prop = shift;
                my ( $app, $val ) = @_;
                my $user = MT->model('author')->load($val)
                    or return $prop->error(
                    MT->translate(
                        '[_1] ( id:[_2] ) does not exists.',
                        MT->translate("Author"),
                        defined $val ? $val : ''
                    )
                    );
                return MT->translate(
                    "All comments by [_1] '[_2]'",
                    (     $user->type == MT::Author::COMMENTER()
                        ? $app->translate("Commenter")
                        : $app->translate("Author")
                    ),
                    (     $user->nickname
                        ? $user->nickname . ' (' . $user->name . ')'
                        : $user->name
                    )
                );
            },
        },
        text => {
            auto      => 1,
            label     => 'Comment Text',
            display   => 'none',
            use_blank => 1,
        },
        for_current_user => {
            base      => '__virtual.hidden',
            label     => 'Comments on My Entries/Pages',
            singleton => 1,
            terms     => sub {
                my ( $prop, $args, $db_terms, $db_args ) = @_;
                my $user = MT->app->user;
                $db_args->{joins} ||= [];
                push @{ $db_args->{joins} },
                    MT->model('entry')->join_on(
                    undef,
                    {   id        => \"= comment_entry_id",
                        author_id => $user->id,
                    },
                    );
            },
        },
        email => {
            auto      => 1,
            display   => 'none',
            label     => 'Email Address',
            use_blank => 1,
            terms     => sub {
                my $prop = shift;
                my ( $args, $db_terms, $db_args ) = @_;
                my $option = $args->{option};
                my $query  = $prop->super(@_);

                my @users = MT->model('author')
                    ->load( $query, { fetchonly => { id => 1 } } );
                my @ids = map { $_->id } @users;
                my $terms;
                if (@ids) {
                    $terms = [ { commenter_id => \@ids }, '-or', ];
                }
                push @$terms, $query;
                return $terms;
            },
        },
        url => {
            auto      => 1,
            display   => 'none',
            label     => 'URL',
            use_blank => 1,
            terms     => sub {
                my $prop = shift;
                my ( $args, $db_terms, $db_args ) = @_;
                my $option = $args->{option};
                my $query  = $prop->super(@_);

                my @users = MT->model('author')
                    ->load( $query, { fetchonly => { id => 1 } } );
                my @ids = map { $_->id } @users;
                return [
                    $query,
                    ( @ids ? ( '-or', { commenter_id => \@ids } ) : () ),
                ];

            },
        },
        commenter_status => {
            label   => 'Commenter Status',
            display => 'none',
            base    => 'commenter.status',
            terms   => sub {
                my $prop = shift;
                my ( $args, $base_terms, $base_args, $opts, ) = @_;
                my $val     = $args->{value};
                my $blog_id = $opts->{blog_ids};
                push @$blog_id, 0 if MT->config->SingleCommunity && $blog_id;
                if ( $val eq 'deleted' ) {
                    $base_args->{joins} ||= [];
                    push @{ $base_args->{joins} },
                        MT->model('author')->join_on(
                        undef,
                        { id => \'is null', },
                        {   type      => 'left',
                            condition => { id => \'= comment_commenter_id' },
                        },
                        );
                    return { commenter_id => \' is not null', };
                }
                elsif ( $val eq 'anonymous' ) {
                    return { commenter_id => \' is null' };
                }
                elsif ( $val eq 'enabled' ) {
                    push @{ $base_args->{joins} },
                        MT->model('author')->join_on(
                        undef,
                        { id => \'= comment_commenter_id', },
                        {   unique => 1,
                            join   => MT->model('permission')->join_on(
                                undef,
                                [   [   {   (   $blog_id
                                                ? ( blog_id => $blog_id )
                                                : ( blog_id => { '>=' => 0 } )
                                            )
                                        },
                                        '-or',
                                        { blog_id => \' IS NULL', }
                                    ],
                                    '-and',
                                    [   [   {   '!author_type!' =>
                                                    MT::Author::AUTHOR(),
                                                '!author_status!' =>
                                                    MT::Author::ACTIVE(),
                                            },
                                            '-and',
                                            [   {   restrictions => \
                                                        ' IS NULL',
                                                },
                                                '-or',
                                                {   restrictions => {
                                                        not_like =>
                                                            "%'comment'%"
                                                    },
                                                },
                                            ],
                                        ],
                                        '-or',
                                        [   {   '!author_type!' =>
                                                    MT::Author::COMMENTER(),
                                            },
                                            '-and',
                                            {   permissions =>
                                                    { like => "%'comment'%" },
                                            },
                                            '-and',
                                            [   {   restrictions => \
                                                        ' IS NULL',
                                                },
                                                '-or',
                                                {   restrictions => {
                                                        not_like =>
                                                            "%'comment'%"
                                                    },
                                                },
                                            ],
                                        ],
                                    ],
                                ],
                                {   type => 'left',
                                    condition =>
                                        { author_id => \'= author_id', },
                                    unique => 1,
                                }
                            ),
                        },
                        );
                }
                elsif ( $val eq 'disabled' ) {
                    push @{ $base_args->{joins} },
                        MT->model('author')->join_on(
                        undef,
                        { id => \'= comment_commenter_id', },
                        {   unique => 1,
                            join   => MT->model('permission')->join_on(
                                undef,
                                [   [   {   (   $blog_id
                                                ? ( blog_id => $blog_id )
                                                : ( blog_id => { '>=' => 0 } )
                                            )
                                        },
                                        '-or',
                                        { blog_id => \' IS NULL', }
                                    ],
                                    '-and',
                                    [   [   {   '!author_type!' =>
                                                    MT::Author::AUTHOR(),
                                                '!author_status!' =>
                                                    MT::Author::INACTIVE(),
                                            },
                                            '-or',
                                            {   restrictions =>
                                                    { like => "%'comment'%" },
                                            },
                                        ],
                                        '-or',
                                        [   {   '!author_type!' =>
                                                    MT::Author::COMMENTER(),
                                                permissions => {
                                                    not_like => "%'comment'%"
                                                },
                                                restrictions =>
                                                    { like => "%'comment'%" },
                                            },
                                        ],
                                    ],
                                ],
                                {   type => 'left',
                                    condition =>
                                        { author_id => \'= author_id', },
                                    unique => 1,
                                }
                            ),
                        },
                        );
                }
                elsif ( $val eq 'pending' ) {
                    push @{ $base_args->{joins} },
                        MT->model('author')->join_on(
                        undef,
                        { id => \'= comment_commenter_id', },
                        {   unique => 1,
                            join   => MT->model('permission')->join_on(
                                undef,
                                [   [   {   (   $blog_id
                                                ? ( blog_id => $blog_id )
                                                : ( blog_id => { '>=' => 0 } )
                                            )
                                        },
                                        '-or',
                                        { blog_id => \' IS NULL', }
                                    ],
                                    '-and',
                                    [   {   '!author_type!' =>
                                                MT::Author::AUTHOR(),
                                            '!author_status!' =>
                                                MT::Author::PENDING(),
                                        },
                                        '-or',
                                        {   '!author_type!' =>
                                                MT::Author::COMMENTER(),
                                            permissions  => \' IS NULL',
                                            restrictions => \' IS NULL',
                                        },
                                    ],
                                ],
                                {   type => 'left',
                                    condition =>
                                        { author_id => \'= author_id', },
                                    unique => 1,
                                }
                            ),
                        },
                        );
                }
                else {
                    my ( $com_terms, $com_args ) = ( {}, {} );
                    $prop->super( $args, $com_terms, $com_args, $opts );
                    $base_args->{joins} ||= [];
                    push @{ $base_args->{joins} },
                        MT->model('author')
                        ->join_on( undef,
                        { id => \'= comment_commenter_id', %$com_terms },
                        $com_args, );
                }
            },
            single_select_options => [
                {   label => MT->translate('Deleted'),
                    value => 'deleted',
                },
                {   label => MT->translate('Enabled'),
                    value => 'enabled',
                },
                {   label => MT->translate('Disabled'),
                    value => 'disabled',
                },
                {   label => MT->translate('Pending'),
                    value => 'pending',
                },
                {   label => MT->translate('__ANONYMOUS_COMMENTER'),
                    value => 'anonymous',
                },
            ],
        },
        id => {
            base      => '__virtual.id',
            condition => sub {0},
        },
        content => {
            base      => '__virtual.content',
            fields    => [qw(url text email ip author)],
            condition => sub {0},
        },
        entry_status => {
            base    => 'entry.status',
            col     => 'entry.status',
            label   => 'Entry/Page Status',
            display => 'none',
        },
        blog_id => {
            auto            => 1,
            col             => 'blog_id',
            display         => 'none',
            filter_editable => 0,
        },
        __mobile => {
            display         => 'force',
            filter_editable => 0,
            bulk_html       => sub {
                my $prop = shift;
                my ( $objs, $app, $opts ) = @_;
                require MT::ListProperty;
                require MT::Util;

                my %entry_ids = map { $_->entry_id => 1 } @$objs;
                my @entries
                    = MT->model('entry')
                    ->load( { id => [ keys %entry_ids ], },
                    { no_class => 1, } );
                my %entries = map { $_->id => $_ } @entries;

                my $no_link = 1;

                my @result;
                for my $obj (@$objs) {
                    my $author_html
                        = _comment_author_html( $prop, $obj, $app, $opts,
                        $no_link );
                    my $entry = $entries{ $obj->entry_id };
                    my $entry_title;
                    if ($entry) {
                        my $no_link = 1;
                        $entry_title = 'to '
                            . MT::ListProperty::make_common_label_html(
                            $entry, $app, 'title', 'No title', $no_link );
                    }
                    else {
                        $entry_title = $app->translate('Deleted');
                    }
                    my $comment_body = MT::Util::remove_html( $obj->text );
                    my $status_icon  = _comment_status_icon($obj);
                    my $date
                        = MT::Util::date_for_listing( $obj->created_on,
                        $app );
                    my $edit_link = $app->uri(
                        mode => 'view',
                        args => {
                            _type   => 'comment',
                            id      => $obj->id,
                            blog_id => $obj->blog_id,
                        }
                    );
                    my $edit = MT->translate('Edit');
                    push @result, qq{
                        <div class="row mb-2">
                            <div class="col-12">$author_html</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-12 font-weight-light">$entry_title</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-12">$comment_body</div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <span class="status mr-3">$status_icon</span>
                                <span class="date font-weight-light">$date</span>
                            </div>
                        </div>
                        <a href="$edit_link"><span class="sr-only visually-hidden">$edit</span></a>
                    };
                }
                return @result;
            },
        },
    };
}

sub list_props_entry {
    return +{
        comment_count => {
            auto         => 1,
            display      => 'default',
            label        => 'Comments',
            filter_label => '__COMMENT_COUNT',
            order        => 800,
            html_link    => sub {
                my $prop = shift;
                my ( $obj, $app, $opts ) = @_;
                return unless $app->can_do('access_to_comment_list');
                return $app->uri(
                    mode => 'list',
                    args => {
                        _type      => 'comment',
                        filter     => 'entry',
                        filter_val => $obj->id,
                        blog_id    => $opts->{blog_id} || 0,
                    },
                );
            },
        },
        commented_on => {
            base          => '__virtual.date',
            label         => 'Date Commented',
            comment_class => 'comment',
            display       => 'none',
            terms         => sub {
                my $prop = shift;
                my ( $args, $db_terms, $db_args ) = @_;
                my $option = $args->{option};
                my $query;
                my $blog = MT->app ? MT->app->blog : undef;
                require MT::Util;
                my $now = MT::Util::epoch2ts( $blog, time() );
                my $from   = $args->{from}   || '';
                my $to     = $args->{to}     || '';
                my $origin = $args->{origin} || '';
                $from =~ s/\D//g;
                $to =~ s/\D//g;
                $origin =~ s/\D//g;
                $from .= '000000' if $from;
                $to   .= '235959' if $to;

                if ( 'range' eq $option ) {
                    $query = [
                        '-and',
                        { op => '>', value => $from },
                        { op => '<', value => $to },
                    ];
                }
                elsif ( 'days' eq $option ) {
                    my $days   = $args->{days};
                    my $origin = MT::Util::epoch2ts( $blog,
                        time - $days * 60 * 60 * 24 );
                    $query = [
                        '-and',
                        { op => '>', value => $origin },
                        { op => '<', value => $now },
                    ];
                }
                elsif ( 'before' eq $option ) {
                    $query = { op => '<', value => $origin . '000000' };
                }
                elsif ( 'after' eq $option ) {
                    $query = { op => '>', value => $origin . '235959' };
                }
                elsif ( 'future' eq $option ) {
                    $query = { op => '>', value => $now };
                }
                elsif ( 'past' eq $option ) {
                    $query = { op => '<', value => $now };
                }
                $db_args->{joins} ||= [];
                push @{ $db_args->{joins} },
                    MT->model( $prop->comment_class )->join_on(
                    undef,
                    { entry_id => \'= entry_id', created_on => $query },
                    { unique   => 1, },
                    );
                return;
            },
            sort => 0,
        },
    };
}

sub list_props_page {
    return +{
        comment_count => {
            display => 'optional',
            base    => 'entry.comment_count',
            order   => 800,
        },
        commented_on => { base => 'entry.commented_on' },
    };
}

sub list_props_website {
    return +{
        comment_count => {
            base => 'blog.comment_count',
            html => sub {
                my $prop = shift;
                my $html = $prop->super(@_);
                $html =~ s/"(.+)"/"$1&filter=current_context"/;
                return $html;
            },
        },
    };
}

sub list_props_author {
    return +{
        comment_count => {
            base         => '__virtual.object_count',
            label        => 'Comments',
            filter_label => '__COMMENT_COUNT',
            display      => 'default',
            order        => 400,
            count_class  => 'comment',
            count_col    => 'commenter_id',
            filter_type  => 'commenter_id',
            raw          => sub {
                my ( $prop, $obj ) = @_;
                MT->model( $prop->count_class )
                    ->count( { commenter_id => $obj->id } );
            },
            html => sub {
                my $prop = shift;
                my ( $obj, $app ) = @_;
                my $count = $prop->raw(@_);
                return $count;
            },
        },
    };
}

sub list_props_commenter {
    return +{
        name => {
            auto       => 1,
            label      => 'Username',
            display    => 'force',
            order      => 100,
            sub_fields => [
                {   class   => 'userpic',
                    label   => 'Userpic',
                    display => 'optional',
                },
                {   class   => 'user-info',
                    label   => 'User Info',
                    display => 'optional',
                },
            ],
            bulk_html => \&_bulk_author_name_html,
        },
        nickname => {
            base  => 'author.nickname',
            order => 200,
        },
        author_name => {
            base  => 'author.author_name',
            order => 400,
        },
        created_on => {
            base  => '__virtual.created_on',
            order => 500,
        },
        modified_on => {
            base  => '__virtual.modified_on',
            order => 600,
        },
        comment_count => {
            base  => 'author.comment_count',
            order => 300,
        },
        email  => { base => 'author.email' },
        status => {
            base    => '__virtual.single_select',
            display => 'none',
            label   => 'Status',
            col     => 'status',
            terms   => sub {
                my ( $prop, $args, $db_terms, $db_args, $opts ) = @_;
                my $val = $args->{value};
                $db_args->{joins} ||= [];
                my $blog_id
                    = MT->config->SingleCommunity ? 0 : $opts->{blog_ids};
                if ( $val eq 'enabled' ) {
                    push @{ $db_args->{joins} },
                        MT->model('permission')->join_on(
                        undef,
                        {   permissions => { like => '%\'comment\'%', },
                            author_id => \'= author_id',
                            blog_id   => $blog_id,
                        }
                        );
                }
                elsif ( $val eq 'disabled' ) {
                    push @{ $db_args->{joins} },
                        MT->model('permission')->join_on(
                        undef,
                        {   restrictions => { like => '%\'comment\'%', },
                            author_id => \'= author_id',
                            blog_id   => $blog_id,
                        }
                        );
                }
                elsif ( $val eq 'pending' ) {
                    push @{ $db_args->{joins} },
                        MT->model('permission')->join_on(
                        undef,
                        {   permissions  => \'IS NULL',        # FOR-EDITOR',
                            restrictions => \'IS NULL',        # FOR-EDITOR',
                            author_id    => \'= author_id',    # FOR-EDITOR',
                            blog_id      => $blog_id,
                        }
                        );
                }
                return;

            },
            single_select_options => [
                {   label => MT->translate('__COMMENTER_APPROVED'),
                    value => 'enabled',
                },
                { label => MT->translate('Banned'),  value => 'disabled', },
                { label => MT->translate('Pending'), value => 'pending', },
            ],
        },
    };
}

sub list_props_member {
    return +{
        comment_count => {
            base         => '__virtual.object_count',
            filter_label => '__COMMENT_COUNT',
            label        => 'Comments',
            count_class  => 'comment',
            count_col    => 'commenter_id',
            filter_type  => 'commenter_id',
            list_screen  => 'comment',
            count_terms  => sub {
                my $prop = shift;
                my ($opts) = @_;
                return { blog_id => $opts->{blog_ids} };
            },
            count_args => { unique => 1, },
            order      => 500,
        },
    };
}

sub system_filters_comment {
    return +{
        current_website => {
            label => 'Comments in This Site',
            items => [ { type => 'current_context' } ],
            order => 50,
            view  => 'website',
        },
        not_spam => {
            label => 'Non-spam comments',
            items =>
                [ { type => 'status', args => { value => 'not_junk' }, }, ],
            order => 100,
        },
        not_spam_in_this_website => {
            label => 'Non-spam comments on this website',
            view  => 'website',
            items => [
                { type => 'current_context' },
                { type => 'status', args => { value => 'not_junk' }, },
            ],
            order => 200,
        },
        pending => {
            label => 'Pending comments',
            items =>
                [ { type => 'status', args => { value => 'pending' }, }, ],
            order => 300,
        },
        published => {
            label => 'Published comments',
            items =>
                [ { type => 'status', args => { value => 'approved' }, }, ],
            order => 400,
        },
        comments_on_my_entry => {
            label => 'Comments on my entries/pages',
            items => sub {
                my $login_user = MT->app->user;
                [   { type => 'for_current_user' },
                    { type => 'current_context' }
                ],
                    ;
            },
            order => 500,
        },
        comments_in_last_7_days => {
            label => 'Comments in the last 7 days',
            items => [
                { type => 'status', args => { value => 'not_junk' }, },
                {   type => 'created_on',
                    args => { option => 'days', days => 7 }
                }
            ],
            order => 600,
        },
        spam => {
            label => 'Spam comments',
            items => [ { type => 'status', args => { value => 'junk' }, }, ],
            order => 700,
        },
        _comments_by_entry => {
            label => sub {
                my $app   = MT->app;
                my $id    = $app->param('filter_val');
                my $entry = MT->model('entry')->load($id);
                return 'Comments by entry: ' . $entry->title;
            },
            items => sub {
                my $app = MT->app;
                my $id  = $app->param('filter_val');
                return [
                    {   type => 'entry',
                        args => { option => 'equal', value => $id }
                    }
                ];
            },
        },
    };
}

sub system_filters_commenter {
    return +{
        enabled => {
            label => 'Enabled Commenters',
            items =>
                [ { type => 'status', args => { value => 'enabled' }, }, ],
            order => 100,
        },
        disabled => {
            label => 'Disabled Commenters',
            items =>
                [ { type => 'status', args => { value => 'disabled' }, }, ],
            order => 200,
        },
        pending => {
            label => 'Pending Commenters',
            items =>
                [ { type => 'status', args => { value => 'pending' }, }, ],
            order => 300,
        },
    };
}

sub system_filters_member {
    return +{
        external_users => {
            label     => 'Externally Authenticated Commenters',
            items     => [ { type => 'type', args => { value => 2 }, }, ],
            condition => sub {
                MT->config->SingleCommunity ? 0 : 1;
            },
            order => 200,
        },
    };
}

sub system_filters_entry {
    return +{
        commented_in_last_7_days => {
            label => 'Entries with Comments Within the Last 7 Days',
            items => [
                {   type => 'commented_on',
                    args => { option => 'days', days => 7 }
                }
            ],
            order => 1100,
        },
    };
}

sub system_filters_page {
    return +{
        commented_in_last_7_days => {
            label => 'Pages with comments in the last 7 days',
            items => [
                {   type => 'commented_on',
                    args => { option => 'days', days => 7 }
                }
            ],
            order => 600,
        },
    };
}

sub roles {
    return +{
        {   name        => MT->translate('Moderator'),
            description => MT->translate('Can comment and manage feedback.'),
            perms       => [ 'comment', 'manage_feedback' ],
        },
        {   name        => MT->translate('Commenter'),
            description => MT->translate('Can comment.'),
            role_mask   => 2**0,
            perms       => ['comment'],
        },
    };
}

sub enable_object_methods {
    return +{
        comment => {
            delete => 1,
            edit   => sub { MT->app->param('id') ? 1 : 0 },
            save   => sub { MT->app->param('id') ? 1 : 0 },
        },
        commenter => { edit => 1, },
    };
}

1;
