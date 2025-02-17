<?php

interface CallableMock
{
	/**
	 * @return T
	 */
	public function __invoke();

	/**
	 * @return T
	 */
	public function __call(string $name, array $arguments);
}