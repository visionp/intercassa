<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace common\behaviors;

use yii\db\BaseActiveRecord;
use yii\db\Expression;
use yii\behaviors\AttributeBehavior;


class UserIdCreatorBehavior extends AttributeBehavior
{

    public $createdAtAttribute = 'user_id';
    public $value;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty($this->attributes)) {
            $this->attributes = [
                BaseActiveRecord::EVENT_BEFORE_INSERT => [$this->createdAtAttribute],
                BaseActiveRecord::EVENT_BEFORE_UPDATE => [$this->createdAtAttribute]
            ];
        }
    }

    /**
     * @inheritdoc
     */
    protected function getValue($event)
    {
        if ($this->value instanceof Expression) {
            return $this->value;
        } else {
            return $this->value !== null ? call_user_func($this->value, $event) : \Yii::$app->user->isGuest ? null : \Yii::$app->user->getId();
        }
    }



    public function evaluateAttributes($event)
    {
        if (!empty($this->attributes[$event->name])) {
            $attributes = (array) $this->attributes[$event->name];
            $value = $this->getValue($event);
            foreach ($attributes as $attribute) {
                // ignore attribute names which are not string (e.g. when set by TimestampBehavior::updatedAtAttribute)
                if (is_string($attribute) && empty($this->owner->$attribute)) {
                    $this->owner->$attribute = $value;
                }
            }
        }
    }

}
