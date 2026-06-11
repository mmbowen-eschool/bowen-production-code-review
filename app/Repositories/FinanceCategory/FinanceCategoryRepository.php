<?php

namespace App\Repositories\FinanceCategory;

use App\Models\FinanceCategory;
use App\Repositories\Base\BaseRepository;

class FinanceCategoryRepository extends BaseRepository implements FinanceCategoryInterface
{
    public function __construct(FinanceCategory $model)
    {
        parent::__construct($model);
    }
}
