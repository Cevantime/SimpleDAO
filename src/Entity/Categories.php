<?php

namespace Entity;

class Categories
{
	/** @var int id */
	private $id;

	/** @var string name */
	private $name;

	/** @var string icon */
	private $icon;


	public function getId()
	{
		return $this->id;
	}


	public function setId(int $id)
	{
		$this->id = $id;
	}


	public function getName()
	{
		return $this->name;
	}


	public function setName(string $name)
	{
		$this->name = $name;
	}


	public function getIcon()
	{
		return $this->icon;
	}


	public function setIcon(string $icon)
	{
		$this->icon = $icon;
	}
}
