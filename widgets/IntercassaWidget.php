<?php
/**
 * Created by PhpStorm.
 * User: VisioN
 * Date: 20.04.2015
 * Time: 11:42
 *
 * Виджет для работы с Интеркассой
 * пример:
 *  echo IntercassaWidget::widget([
 *       'nameForm' => 'nameForm',
 *       'classForm' => 'cssClassName',
 *       'classButton' => 'cssClassName',
 *       'classInput' => 'cssClassName',
 *       'nameButton' => 'nameButton',
 *       'labelButton' => 'Отправить',
 *       'amount' => 550,
 *       'description' => 'Пополнение баланса 2',
 *       'labelButton' => 'Оплатить',
 *       'config_fields' => [
 *       .....
 *      ]
 *  ]);
 */

namespace vision\interkassa\widgets;

use yii\base\Widget;
use yii\helpers\Html;
use common\exceptions\ExceptionsIntercassa;

class IntercassaWidget extends Widget {

    public $amount;
    public $nameForm    = 'payment';
    public $description = null;
    public $nameButton  = 'send_pay';
    public $labelButton = 'Отправить';
    public $classForm   = 'payment';
    public $classInput  = 'form-control';
    public $classButton = 'btn btn-primary';

    public $accept_charset = 'UTF-8';
    public $config_fields;
    public $is_edit_amount = true;

    private $method      = 'post';


    protected function getConfigFields()
    {
        $_config_fields = \Yii::$app->intercassa->configFields;
        $config_fields = array_merge(
            $_config_fields,
            isset($this->config_fields) && is_array($this->config_fields) ? $this->config_fields :[]
        );
        if(!$this->is_edit_amount && !isset($this->amount) && !$this->amount) {
            throw new ExceptionsIntercassa('Не указано сумму транзакции');
        }
        $this->amount = $this->amount ? $this->amount : 0;
        $config_fields['ik_pm_no'] = \Yii::$app->intercassa->newPay();
        $config_fields['ik_am']    = $this->amount;

        if($this->description ) {
            $config_fields['ik_desc']  = $this->description;
        }

        if(isset($_config_fields->interAction) && $_config_fields->interAction) {
            $config_fields['ik_ia_u'] = \Yii::$app->urlManager ->createAbsoluteUrl($_config_fields->interAction);
        }

        return $config_fields;
    }


    protected function getFields() {
        $contentFields = '';
        $configFields = $this->getConfigFields();
        foreach($configFields as $name => $val){
            if($val == null || $name == null) {
                continue;
            }
            if(in_array($name, array('ik_am')) && $this->is_edit_amount) {

                $params = Array('class'    => $this->classInput);
                if(!$this->is_edit_amount) {
                    $params['disabled'] = 1;
                }
                $contentFields .= Html::input('number', $name, $val, $params);
                if($this->is_edit_amount) {
                    continue;
                }
            }
            $contentFields .= Html::hiddenInput($name, $val);
        }
        return $contentFields;
    }


    protected function getMainContent() {
        $content = '';
        $content .= Html::beginForm(\Yii::$app->intercassa->actionUrl, $this->method, [
            'class'  => $this->classForm,
            'name'   => $this->nameForm,
            'accept-charset' =>$this->accept_charset
        ]);
        $content .= $this->getFields();
        $content .= Html::submitButton($this->labelButton, [
            'class' => $this->classButton,
            'name'  => $this->nameButton
        ]);
        $content .= Html::endForm();
        return $content;
    }


    public function init()
    {
        parent::init();
    }


    public function run()
    {
        return $this->getMainContent();
    }

} 