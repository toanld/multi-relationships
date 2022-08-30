<?php

namespace Toanld\Relationships\Database\Eloquent;

use Toanld\Relationship\MultiRelationship;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Model extends Eloquent
{
    use MultiRelationship;
}
