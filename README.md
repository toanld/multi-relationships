
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

### Example

```php
    $data = Test::with(['category:id,name'])->limit(2)->get();
    foreach ($data as $row){
        //return model category relate with field cat_1
        $category = $row->getRelationshipValue($row->cat_1,'category');
    }
```
