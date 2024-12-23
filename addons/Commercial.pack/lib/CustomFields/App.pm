# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package CustomFields::App;

use strict;
use warnings;
use CustomFields::Util;
use CustomFields::App::CMS;

sub init_app {
    my ( $cb, $app ) = @_;

    CustomFields::Util::install_field_tags;

    if ( $app->id eq 'cms' ) {
        CustomFields::App::CMS::init_app( $cb, $app );
    }
}

1;
