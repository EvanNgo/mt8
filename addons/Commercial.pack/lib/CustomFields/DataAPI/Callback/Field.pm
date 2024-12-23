# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package CustomFields::DataAPI::Callback::Field;

use strict;
use warnings;

use CustomFields::App::CMS;

sub can_view {
    my $eh = shift;
    my ( $app, $id, $objp ) = @_;
    return 0 unless ($id);
    my $obj = $objp->force() or return 0;

    my $user = $app->user or return;
    return 1 if $user->is_superuser;

    # The following user can retrieve website/blog fields.
    # * Having any permissions except for 'comment' in system scope
    #   or in website/blog scope.

    # The following user can retrieve system fields.
    # * Having any permissions except for 'comment' in any scope.

    return $app->model('permission')->exist(
        {   author_id   => $user->id,
            permissions => { not => "'comment'" },
            $obj->blog_id ? ( blog_id => [ 0, $obj->blog_id ] ) : (),
        }
    );
}

sub can_list {
    my ( $cb, $app, $terms, $args, $options ) = @_;

    my $user = $app->user or return;
    my $endpoint = $app->current_endpoint;

    # list_all_fields endpoint will be checked
    # in 'data_api_pre_load_filtered_list.field' callback.
    unless ( $endpoint->{route} && $endpoint->{route} =~ m!/:site_id/! ) {
        return 1;
    }

    my $blog_id = $app->blog ? $app->blog->id : 0;

    # The following user can retrieve website/blog fields.
    # * Having any permissions except for 'comment' in system scope
    #   or in website/blog scope.

    # The following user can retrieve system fields.
    # * Having any permissions except for 'comment' in any scope.

    return $app->model('permission')->exist(
        {   author_id   => $user->id,
            permissions => { not => "'comment'" },
            $blog_id ? ( blog_id => [ 0, $blog_id ] ) : (),
        }
    );
}

sub cms_pre_load_filtered_list {
    my ( $cb, $app, $filter, $load_options, $cols ) = @_;

    $load_options->{editable_filters} ||= [];
    push(
        @{ $load_options->{editable_filters} },
        sub {
            my ( $objs, $options ) = @_;
            CustomFields::App::CMS::editable_filter( $app, $objs, $options );
        }
    );

    # The request of list_fields endpoint has been already checked
    # in 'data_api_list_permission_filter.field' callback.
    if ( exists $load_options->{blog} ) {
        if ( 'system' eq ( lc( $app->param('includeShared') || '' ) ) ) {
            my $terms = $load_options->{terms};
            my $blog_ids;
            push @$blog_ids, $load_options->{blog}->id;
            push @$blog_ids, 0;
            $terms->{blog_id}      = $blog_ids;
            $load_options->{terms} = $terms;
        }
        return;
    }

    my $user = $app->user;
    return if $user->is_superuser;
    return if $user->permissions(0)->can_do('edit_templates');

    require MT::Permission;
    my $iter = MT::Permission->load_iter(
        {   author_id   => $user->id,
            permissions => { not => "'comment'" },
        },
    );

    my $blog_ids;
    while ( my $perm = $iter->() ) {
        push @$blog_ids, $perm->blog_id;
    }
    push @$blog_ids, 0;

    my $terms = $load_options->{terms};
    $terms->{blog_id} = $blog_ids
        if $blog_ids;
    $load_options->{terms} = $terms;
}

sub save_filter {
    my ( $eh, $app, $obj, $orig ) = @_;

    # Check whether some fields are empty or not.
    my @not_empty_fields = (
        { field => 'obj_type', parameter => 'systemObject' },
        { field => 'name',     parameter => 'name' },
        { field => 'type',     parameter => 'type' },
        { field => 'basename', parameter => 'basename' },
        { field => 'tag',      parameer  => 'tag' },
    );

    for (@not_empty_fields) {
        my $field = $_->{field};
        if ( !defined( $obj->$field ) || $obj->$field eq '' ) {
            return $eh->error(
                $app->translate(
                    'A parameter "[_1]" is required.',
                    $_->{parameter}
                )
            );
        }
    }

    # Check whether tag is valid or not.
    if ( $obj->tag =~ m/[^0-9A-Za-z_]/ ) {
        return $eh->error(
            $app->translate(
                "The template tag '[_1]' is an invalid tag name.",
                $obj->tag
            )
        );
    }

    # Check whether type is valid or not.
    my $reg_types = $app->registry('customfield_types') or return;
    my $types = _grep_by_context( $app, $reg_types );
    if ( !( grep { $_ eq $obj->type } @$types ) ) {
        return $eh->error(
            $app->translate( 'The type "[_1]" is invalid.', $obj->type ) );
    }

    # If the type having options_field is selected,
    # options field should not be empty.
    if ( exists $reg_types->{ $obj->type }{options_field} ) {
        if ( !defined( $obj->options ) || $obj->options eq '' ) {
            return $eh->error(
                $app->translate(
                    'A parameter "[_1]" is required.', 'options'
                )
            );
        }
    }

    # Check whether systemObject (obj_type) is valid or not.
    my $reg_objs = $app->registry('customfield_objects') or return;
    my $objs = _grep_by_context( $app, $reg_objs );
    if ( !( grep { $_ eq $obj->obj_type } @$objs ) ) {
        return $eh->error(
            $app->translate(
                'The systemObject "[_1]" is invalid.',
                $obj->obj_type
            )
        );
    }

    # Is that tag already defined by some other field?
    MT->model('field')->validates_uniqueness_of_tag(
        $eh,
        {   tag      => $obj->tag      || undef,
            blog_id  => $obj->blog_id  || undef,
            id       => $obj->id       || undef,
            basename => $obj->basename || undef,
            obj_type => $obj->obj_type || undef,
            type     => $obj->type     || undef,
        }
    ) or return;    # has error

    # Is that tag already defined by core or some other plugin?
    my @components
        = grep { lc $_->id ne 'commercial' } MT::Component->select();
COMPONENT: for my $component (@components) {
        my $comp_tags = $component->registry("tags");
    SET: for my $type (qw( block function )) {
            my $tags = $comp_tags->{$type}
                or next SET;
        TAG: for my $tag ( keys %$tags ) {
                return $eh->error(
                    $app->translate(
                        "The template tag '[_1]' is already in use.",
                        $obj->tag,
                    )
                ) if $obj->tag eq lc $tag;
            }
        }
    }

    # Is the basename already used?
    if ( defined( $obj->basename ) && $obj->basename ne '' ) {
        my $basename = $obj->basename;
        my $basename_is_unique
            = MT->model('field')->make_unique_field_basename(
            stem       => $basename,
            field_id   => $obj->id || 0,
            blog_id    => $obj->blog_id || 0,
            type       => $obj->type || q{},
            check_only => 1,
            );
        my $blog = $app->blog;
        my $blog_id = ( $blog && $blog->id ) ? $blog->id : 0;
        my $level
            = $blog
            ? (
              $blog->is_blog
            ? $app->translate('blog and the system')
            : $app->translate('website and the system')
            )
            : $app->translate('Movable Type');
        return $eh->error(
            $app->translate(
                "The basename '[_1]' is already in use. It must be unique within this [_2].",
                $basename,
                $level
            )
        ) if !$basename_is_unique;
    }

    return $eh->error(
        $app->translate(
            "You must select other type if object is the comment.")
        )
        if $obj->obj_type eq 'comment'
        && ( $obj->type eq 'audio'
        || $obj->type eq 'image'
        || $obj->type eq 'video'
        || $obj->type eq 'file' );

    return 1;
}

sub _grep_by_context {
    my ( $app, $reg ) = @_;

    my @objs;
    for my $o ( keys %$reg ) {
        my $context = $reg->{$o}{context};
        if ( ref $context ) {
            if ( $app->blog && $app->blog->id ) {
                next
                    if $app->blog->is_blog
                    && !( grep { $_ eq 'blog' } @$context );
                next
                    if !$app->blog->is_blog
                    && !( grep { $_ eq 'website' } @$context );
            }
            else {
                next unless grep { $_ eq 'system' } @$context;
            }
        }
        elsif ($context
            && $context eq 'system'
            && $app->blog
            && $app->blog->id )
        {
            next;
        }
        elsif ($context
            && $context eq 'blog'
            && !( $app->blog && $app->blog->id ) )
        {
            next;
        }

        push @objs, $o;
    }

    return \@objs;
}

# Set default value of custom field.
sub pre_save {
    my ( $cb, $app, $obj, $original ) = @_;

    if ( $obj->type eq 'checkbox' && !$obj->default ) {
        $obj->default(0);
    }
    elsif ( $obj->type eq 'url' && !defined $obj->default ) {
        $obj->default('http://');
    }

    return 1;
}

1;

__END__

=head1 NAME

CustomFields::DataAPI::Callback::Field - Movable Type class for Data API's callbacks about the MT::Field.

=head1 AUTHOR & COPYRIGHT

Please see the I<MT> manpage for author, copyright, and license information.

=cut
