<?php
/**
 * Created by PhpStorm.
 * User: VisioN
 * Date: 20.04.2015
 * Time: 15:40
 *
 * Intercassa exception
 * for specific exceptions
 *
 */

namespace vision\interkassa\exceptions;


class IntercassaException extends  \yii\base\UserException {

    public $post;


    public function __construct($message = null, $code = 0, \Exception $previous = null, $post = [])
    {
        $this->post = serialize($post);
        parent::__construct($message, $code, $previous);
    }
}