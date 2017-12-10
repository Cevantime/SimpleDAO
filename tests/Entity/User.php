<?php

namespace Tests\Entity;

/**
 * Description of User
 *
 * @author Etudiant
 */
class User
{
    private $id;
    private $firstname;
    private $lastname;
    private $email;
    /**
     *
     * @var Category
     */
    private $category;
    
    public function getId()
    {
        return $this->id;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function getLastname()
    {
        return $this->lastname;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
    }

    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }
    
    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory(Category $category)
    {
        $this->category = $category;
    }


}
