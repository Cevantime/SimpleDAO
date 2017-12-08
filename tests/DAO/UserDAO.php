<?php

namespace Tests\DAO;
/**
 * Description of UserDAO
 *
 * @author Etudiant
 */
class UserDAO extends \SimpleDAO\DAO
{
    protected $hasOne = [
        'category'
    ];
    
}
