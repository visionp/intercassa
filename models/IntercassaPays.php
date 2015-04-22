<?php

namespace vision\interkassa\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use common\behaviors\UserIdCreatorBehavior;

/**
 * This is the model class for table "intercassa_pays".
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $invoice_state
 * @property string $invoice_id
 * @property string $transaction_id
 * @property string $checkout_purse_id
 * @property string $currency
 * @property string $amount
 * @property string $paysystem_price
 * @property string $checkout_refund
 * @property string $description
 * @property string $invoice_created
 * @property string $invoice_processed
 * @property string $payway_via
 * @property integer $created_at
 * @property integer $updated_at
 *
 * @property User $user
 */
class IntercassaPays extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'intercassa_pays';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'created_at', 'updated_at'], 'integer'],
            [['amount', 'paysystem_price', 'checkout_refund'], 'number'],
            [['invoice_state', 'invoice_created', 'invoice_processed'], 'string', 'max' => 25],
            [['invoice_id', 'transaction_id', 'checkout_purse_id'], 'string', 'max' => 50],
            [['currency'], 'string', 'max' => 10],
            [['description'], 'string', 'max' => 250],
            [['payway_via'], 'string', 'max' => 100]
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
            UserIdCreatorBehavior::className()
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'invoice_state' => Yii::t('app', 'Invoice State'),
            'invoice_id' => Yii::t('app', 'Invoice ID'),
            'transaction_id' => Yii::t('app', 'Transaction ID'),
            'checkout_purse_id' => Yii::t('app', 'Checkout Purse ID'),
            'currency' => Yii::t('app', 'Currency'),
            'amount' => Yii::t('app', 'Amount'),
            'paysystem_price' => Yii::t('app', 'Paysystem Price'),
            'checkout_refund' => Yii::t('app', 'Checkout Refund'),
            'description' => Yii::t('app', 'Description'),
            'invoice_created' => Yii::t('app', 'Invoice Created'),
            'invoice_processed' => Yii::t('app', 'Invoice Processed'),
            'payway_via' => Yii::t('app', 'Payway Via'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}
