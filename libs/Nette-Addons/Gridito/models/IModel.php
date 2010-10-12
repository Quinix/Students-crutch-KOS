<?php


/**
 * Data model
 *
 * @author Jan Marek
 * @license MIT
 */
interface IModel extends IteratorAggregate, Countable
{
	const ASC = "asc";
	const DESC = "desc";

	public function setupGrid(Grid $grid);

	public function processActionParam($param);

	public function setSorting($column, $type);

	public function setLimit($limit);

	public function setOffset($offset);

}