# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package Comments::DataAPI::Resource::v5::Blog;

use strict;
use warnings;

sub fields {
    [
        {
            # Not boolean.
            name => 'moderateComments',
            type => 'MT::DataAPI::Resource::DataType::Integer',
        },
    ];
}

1;
