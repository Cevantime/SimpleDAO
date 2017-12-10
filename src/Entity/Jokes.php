<?php

namespace Entity;

class Jokes
{
	/** @var int id */
	private $id;

	/** @var string title */
	private $title;

	/** @var string text */
	private $text;

	/** @var string image */
	private $image;

	/** @var \Entity\Categories categories */
	private $categories;


	public function getId()
	{
		return $this->id;
	}


	public function setId(int $id)
	{
		$this->id = $id;
	}


	public function getTitle()
	{
		return $this->title;
	}


	public function setTitle(string $title)
	{
		$this->title = $title;
	}


	public function getText()
	{
		return $this->text;
	}


	public function setText(string $text)
	{
		$this->text = $text;
	}


	public function getImage()
	{
		return $this->image;
	}


	public function setImage(string $image)
	{
		$this->image = $image;
	}


	public function getCategories()
	{
		return $this->categories;
	}


	public function setCategories(Categories $categories)
	{
		$this->categories = $categories;
	}
}
