# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package CustomFields::Upgrade;

use strict;
use warnings;
use CustomFields::Util qw( save_meta );

sub customfields_move_meta {
    my $plugin = shift;
    my ($obj_type) = @_;

    # CF21b stores authors data as "_NN" ('author' isn't there)!
    my $t = $obj_type eq 'author' ? q() : $obj_type;

    require MT::PluginData;
    my $pd_iter = MT::PluginData->load_iter(
        {   plugin => 'CustomFields',
            key    => "${t}_%",
        },
        { like => { key => 1 } }
    );

    my @pd_ids;
    while ( my $pd = $pd_iter->() ) {
        if ( $pd->key =~ /^${t}_(\d+)$/ ) {
            my $id      = $1;
            my $pd_data = $pd->data;
            next unless $pd_data;
            my $old_meta = $pd_data->{customfields};
            unless ($old_meta) {
                $old_meta = $pd_data;
            }
            my $obj = MT->model($obj_type)->load($id);
            next unless $obj;
            save_meta( $obj, $old_meta );
            push @pd_ids, $pd->id;
        }
    }

    MT::PluginData->remove( { id => \@pd_ids } )
        if @pd_ids;
    1;
}

sub customfields_page_meta {
    my $plugin = shift;

    $plugin->progress(
        $plugin->translate_escape('Moving metadata storage for pages...') );
    customfields_move_meta( $plugin, 'page' );
}

sub customfields_remove_plugindata {
    my $plugin = shift;

    $plugin->progress(
        $plugin->translate_escape(
            'Removing CustomFields display-order from plugin data...')
    );

    require MT::PluginData;
    my $iter = MT::PluginData->load_iter( { plugin => 'CustomFields' } );
    while ( my $pdata = $iter->() ) {
        next unless $pdata->key =~ /field_order_\d/;
        $pdata->remove;
    }
}

sub remove_unlinked_field {
    my $plugin = shift;

    $plugin->progress(
        $plugin->translate_escape('Removing unlinked CustomFields...') );

    my @blog_ids = MT->model('blog')->load(
        undef,
        {   fetchonly => { id => 1, },
            no_class  => 1,
        }
    );

    my ( $terms, $args );
    if (@blog_ids) {
        my @ids = map { $_->id } @blog_ids;
        push @ids, 0;
        $terms->{blog_id} = \@ids;
        $args->{not} = { blog_id => 1 };
    }
    else {
        $terms->{blog_id} = { 'not' => 0 };
    }

    require CustomFields::Field;
    CustomFields::Field->driver->direct_remove( 'CustomFields::Field', $terms,
        $args );
}

1;
