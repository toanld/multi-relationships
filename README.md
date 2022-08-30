
### Syntax

```php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Toanld\Relationship\MultiRelationships;

class A extends Model
{
    use MultiRelationships;
    
    public function category(){
        return $this->hasOne(Category::class,'id',['cat_3','cat_2','cat_1','list_cat']);
    }
}
```