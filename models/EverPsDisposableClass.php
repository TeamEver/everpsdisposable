<?php
/**
 * Project : everpsdisposable
 * @author Team Ever
 * @copyright Team Ever
 * @license   Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * @link https://www.team-ever.com
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class EverPsDisposableClass extends ObjectModel
{
    public $id_everpsdisposable;
    public $disposable_email;
    public $active;

    public static $definition = array(
        'table' => 'everpsdisposable',
        'primary' => 'id_everpsdisposable',
        'multilang' => false,
        'fields' => array(
            'disposable_email' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true
            ),
            'active' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isBool'
            ),
        )
    );

    public static function getByDisposable($disposable_email, $active = 1)
    {
        $sql = new DbQuery;
        $sql->select('id_everpscustomerpro');
        $sql->from('everpsdisposable', 'ep');
        $sql->where('ep.disposable_email = "'.(int)$disposable_email.'"');
        $sql->where('ep.disposable_email = '.(bool)$active);
        return new self(Db::getInstance()->getValue($sql));
    }
}
