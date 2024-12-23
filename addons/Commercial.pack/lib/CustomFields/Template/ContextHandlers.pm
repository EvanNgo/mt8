# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

# Original Copyright (c) 2005-2007 Arvind Satyanarayan

package CustomFields::Template::ContextHandlers;

use strict;
use warnings;
use CustomFields::Util qw( get_meta field_loop _get_html load_meta_to_cache );
use MT::Util qw( asset_cleanup );

sub _hdlr_app_fields {
    my ( $ctx, $args, $cond ) = @_;
    my $plugin = MT->component("Commercial");

    if ( !$ctx->var('field_loop') ) {
        my %param = (
            blog_id => $args->{blog_id} || $ctx->var('blog_id'),
            object_type => $args->{object_type}
                || $ctx->var('object_type')
                || $ctx->var('type'),
            object_id => $args->{object_id} || $ctx->var('id')
        );
        $ctx->var( 'field_loop', field_loop(%param) );
    }

    my $field_loop_tmpl = $args->{tmpl}
        || File::Spec->catdir( $plugin->path, 'tmpl', 'field_loop.tmpl' );

    return $ctx->build(<<"EOT");
<div id="customfields-loop">
    <mt:include name="$field_loop_tmpl">
</div>
EOT
}

sub find_stashed_by_type {
    my ( $ctx, $obj_type ) = @_;
    my $obj = $ctx->stash($obj_type);

    # So if $obj doesn't exist, lets try to figure out if we can find it

    # First lets check for subclasses of MT::Entry and MT::Category which
    # stash as entries or categories respectively.
    if ( !$obj ) {
        my $class = MT->model($obj_type);
        $obj = $ctx->stash( $class->datasource );
        if ( $obj_type eq 'website' && $obj ) {
            $obj = $obj->website
                or die $ctx->_no_parent_website_error();
        }
    }

    # Otherwise lets try some hacks
    if ( !$obj ) {
        my $entry = $ctx->stash('entry');
        if ( $obj_type eq 'category' ) {
            $obj
                = $entry ? $entry->category : $ctx->stash('archive_category');
        }
        elsif ( $obj_type eq 'author' ) {
            $obj = $entry ? $entry->author : undef;
        }
    }

    return $obj;
}

sub _hdlr_customfields {
    my ( $ctx, $args, $cond ) = @_;
    my ($obj_type) = lc( $ctx->stash('tag') ) =~ m/(.*)customfields/;
    my $blog_id    = $ctx->stash('blog_id');
    my $builder    = $ctx->stash('builder');
    my $tokens     = $ctx->stash('tokens');
    my $res        = '';

    my %exclude;
    if ( my $excl = $args->{exclude} ) {
        $exclude{$_} = 1 for split /\s*,\s*/, $excl;
    }
    my %include;
    my $i = 0;
    if ( my $incl = $args->{include} ) {
        $include{$_} = $i++ for split /\s*,\s*/, $incl;
    }
    my @incl_list = keys %include;

    require CustomFields::Field;
    my $terms = {
        obj_type => $obj_type,
        blog_id  => ( $blog_id ? [ $blog_id, 0 ] : 0 ),
        ( %include ? ( name => \@incl_list ) : () )
    };
    my @fields = CustomFields::Field->load($terms);

    if (%include) {
        @fields
            = sort { $include{ $a->name } <=> $include{ $b->name } } @fields;
    }

    foreach my $field (@fields) {
        next if %exclude && exists( $exclude{ $field->name } );
        local $ctx->{__stash}{field} = $field;
        defined( my $out = $builder->build( $ctx, $tokens ) )
            or return $ctx->error( $builder->errstr );
        $res .= $out;
    }

    $res;
}

sub _hdlr_customfield_basename {
    my ($ctx) = @_;
    my $field = $ctx->stash('field')
        or return _no_field($ctx);
    return $field->basename;
}

sub _hdlr_customfield_name {
    my ($ctx) = @_;
    my $field = $ctx->stash('field')
        or return _no_field($ctx);
    return $field->name;
}

sub _hdlr_customfield_description {
    my ($ctx) = @_;
    my $field = $ctx->stash('field')
        or return _no_field($ctx);
    return $field->description;
}

sub _hdlr_customfield_value {
    my ( $ctx, $args ) = @_;
    my $field = $ctx->stash('field')
        or return _no_field($ctx);

    my $obj = eval { find_stashed_by_type( $ctx, $field->obj_type ) };
    return $ctx->error($@) if $@;

    return $ctx->error(
        MT->translate(
            "Are you sure you have used a '[_1]' tag in the correct context? We could not find the [_2]",
            $ctx->stash('tag'),
            $field->obj_type
        )
    ) unless ref $obj;

    my $obj_type = $obj->datasource;
    my $obj_id   = $obj->id;
    my $res      = '';

    load_meta_to_cache($obj);
    my $value = get_meta( $obj, $field->basename );
    if ( defined $value ) {
        my $fld_type = $field->type;
        if ( $fld_type eq 'textarea' ) {
            my $convert_breaks
                = exists $args->{convert_breaks} ? $args->{convert_breaks}
                : ( $obj_type eq 'entry' && defined $obj->convert_breaks )
                ? $obj->convert_breaks
                : $ctx->stash('blog') ? $ctx->stash('blog')->convert_paras
                :                       '__default__';

            # Strip the mt:asset-id attribute from any span tags...
            if ( $value =~ m/\smt:asset-id="\d+"/
                && !$args->{no_asset_cleanup} )
            {
                $value = asset_cleanup($value);
            }

            if ($convert_breaks) {
                $convert_breaks = '__default__' if $convert_breaks eq '1';
                $value = MT->apply_text_filters( $value, [$convert_breaks],
                    $ctx );
            }
        }
        elsif ( $fld_type eq 'datetime' ) {
            $value =~ s/\D//g;
            return q() unless $value;
            if ( length $value == 8 ) {
                $value .= '000000';
            }
            if ( !$args->{form_field} ) {
                $args->{ts} = $value;

                if ( !exists $args->{format} ) {
                    if ( 'datetime' eq $field->options ) {
                        $args->{format} = '%x %X';
                    }
                    elsif ( 'date' eq $field->options ) {
                        $args->{format} = '%x';
                    }
                    elsif ( 'time' eq $field->options ) {
                        $args->{format} = '%X';
                    }
                }

                require MT::Template::Context;
                $value = $ctx->invoke_handler( 'date', $args );
            }
        }
        else {
            if ( $args->{label} ) {
                my $type_obj = MT->registry( 'customfield_types', $fld_type );
                if ( $type_obj->{options_delimiter} ) {
                    my $expr = '\s*'
                        . quotemeta( $type_obj->{options_delimiter} ) . '\s*';
                    my @options = split /$expr/, $field->options;
                    my $value_label;
                    foreach my $option (@options) {
                        my $label = $option;
                        ( $option, $label ) = split /\s*=\s*/, $option, 2
                            if $option =~ m/=/;
                        my $option_row
                            = { option => $option, label => $label };
                        if ( $value eq $option ) {
                            $value_label = $label;
                            last;
                        }
                    }
                    $value = $value_label;
                }
            }
        }
        $res = $value;
    }

    # Strip the mt:asset-id attribute from any span tags...
    if ( $res =~ m/\smt:asset-id="\d+"/ && !$args->{no_asset_cleanup} ) {
        $res = asset_cleanup($res);
    }

    $res;
}

sub _hdlr_customfield_asset {
    my ( $ctx, $args, $cond ) = @_;

    my $tokens  = $ctx->stash('tokens');
    my $builder = $ctx->stash('builder');
    my $res     = '';

    $args->{no_asset_cleanup} = 1;
    my $value = _hdlr_customfield_value(@_);

    return '' unless $value;

    require MT::Asset;
    while ( $value
        =~ m!<form[^>]*?\smt:asset-id=["'](\d+)["'][^>]*?>(.+?)</form>!gis )
    {
        my $id = $1;

        my $asset = MT::Asset->load($id);
        next unless $asset;

        local $ctx->{__stash}{asset} = $asset;
        defined( my $out = $builder->build( $ctx, $tokens ) )
            or return $ctx->error( $builder->errstr );
        $res .= $out;
    }

    $res;
}

sub find_field_by_tag {
    my ( $ctx, $tag ) = @_;

    $tag ||= $ctx->stash('tag')
        or return;

    my $field = $ctx->stash('field');
    return $field
        if $field && lc $field->tag eq $tag;

    my $blog_id = $ctx->stash('blog_id');
    unless ($blog_id) {
        my $blog = $ctx->stash('blog');
        $blog_id = $blog->id if $blog;
    }
    my $blog_ids = $blog_id ? [ $blog_id, 0 ] : 0;

    return MT->model('field')->load(
        {   blog_id => $blog_ids,
            tag     => lc $tag,
        }
    );
}

sub _hdlr_customfield_value_by_tag {
    my ($ctx) = @_;
    my $field = find_field_by_tag($ctx)
        or return _no_field($ctx);
    local $ctx->{__stash}{field} = $field;
    return _hdlr_customfield_value(@_);
}

sub _hdlr_customfield_asset_by_tag {
    my ($ctx) = @_;

    # Asset tags end in "asset."
    my $tag = $ctx->stash('tag');
    $tag =~ s{ asset \z }{}xmsi;

    my $field = find_field_by_tag( $ctx, $tag )
        or return _no_field($ctx);
    local $ctx->{__stash}{field} = $field;
    return _hdlr_customfield_asset(@_);
}

sub _hdlr_customfield_html {
    my $ctx = shift;
    my ($args) = @_;

    my $field = $ctx->stash('field')
        or return _no_field($ctx);

    my ( $type, $basename ) = ( $field->type, $field->basename );
    my $type_obj = MT->registry( 'customfield_types', $type );

    my $row = $field->get_values();
    $row->{blog_id} ||= 0;
    my $field_value;
    my $app = MT->instance();
    if (   ( ( ref $app ) =~ /^MT::App::/ )
        && ( $app->param('customfield_beacon') )
        && ( $ctx->var('app_page_template') ) )
    {
        $field_value = $app->param("customfield_$basename");
    }
    else {
        $args ||= {};
        $field_value
            = _hdlr_customfield_value( $ctx, { form_field => 1, %$args } );
    }
    $field_value = $field->default unless defined $field_value;
    $row->{field_value} = $field_value;
    $row->{value}       = $field_value;
    if ( $type_obj->{options_delimiter} ) {
        my @option_loop;
        my $expr
            = '\s*' . quotemeta( $type_obj->{options_delimiter} ) . '\s*';
        my @options = split /$expr/, $field->options;
        foreach my $option (@options) {
            my $label = $option;
            ( $option, $label ) = split /\s*=\s*/, $option, 2
                if $option =~ m/=/;
            my $option_row = { option => $option, label => $label };
            $option_row->{is_selected}
                = defined $row->{value} ? ( $row->{value} eq $option ) : 0;
            push @option_loop, $option_row;
        }
        $row->{option_loop} = \@option_loop;
    }
    $row->{show_field}
        = ( $field->obj_type eq 'entry' || $field->obj_type eq 'page' )
        ? 0
        : 1;
    $row->{show_hint} = $type ne 'checkbox' ? 1 : 0;
    $row->{field_id} = $row->{field_name} = "customfield_$basename";

    return _get_html( $type, 'field_html', $row );
}

sub _no_field {
    my ($ctx) = @_;
    return $ctx->error(
        MT->translate(
            "You used an '[_1]' tag outside of the context of the correct content; ",
            $ctx->stash('tag')
        )
    );
}

sub _hdlr_customfield_is_required {
    my ( $ctx, $args, $cond ) = @_;
    my $field = $ctx->stash('field')
        or return _no_field($ctx);

    return '' unless $field;
    return $field->required ? 1 : 0;
}

1;
