# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments::Upgrade;

use strict;
use warnings;

sub seed_database {
    my ( $cb, $self, %param ) = @_;

    _install_comment_roles($self);

    return 1;
}

sub _install_comment_roles {
    my $self = shift;

    $self->progress(
        $self->translate_escape("Creating initial comment roles...") );

    require MT::Role;

    foreach my $r ( _comment_roles() ) {
        my $role = MT::Role->new();
        $role->name( $r->{name} );
        $role->description( $r->{description} );
        $role->clear_full_permissions;
        $role->set_these_permissions( $r->{perms} );
        if ( $r->{name} =~ m/^System/ ) {
            $role->is_system(1);
        }
        $role->role_mask( $r->{role_mask} ) if exists $r->{role_mask};
        $role->save
            or return $self->error( $role->errstr );
    }
}

sub _comment_roles {
    return (
        {   name        => MT->translate('Moderator'),
            description => MT->translate('Can comment and manage feedback.'),
            perms       => [ 'comment', 'manage_feedback' ],
        },
        {   name        => MT->translate('Commenter'),
            description => MT->translate('Can comment.'),
            role_mask   => 2**0,
            perms       => ['comment'],
        },
    );
}

1;
