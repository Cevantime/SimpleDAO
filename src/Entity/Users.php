<?php

namespace Entity;

class Users
{
	/** @var int id */
	private $id;

	/** @var string username */
	private $username;

	/** @var string password */
	private $password;

	/** @var string email */
	private $email;

	/** @var string role */
	private $role;

	/** @var string salt */
	private $salt;


	public function getId()
	{
		return $this->id;
	}


	public function setId(int $id)
	{
		$this->id = $id;
	}


	public function getUsername()
	{
		return $this->username;
	}


	public function setUsername(string $username)
	{
		$this->username = $username;
	}


	public function getPassword()
	{
		return $this->password;
	}


	public function setPassword(string $password)
	{
		$this->password = $password;
	}


	public function getEmail()
	{
		return $this->email;
	}


	public function setEmail(string $email)
	{
		$this->email = $email;
	}


	public function getRole()
	{
		return $this->role;
	}


	public function setRole(string $role)
	{
		$this->role = $role;
	}


	public function getSalt()
	{
		return $this->salt;
	}


	public function setSalt(string $salt)
	{
		$this->salt = $salt;
	}
}
