<?php

namespace App\Model;

use Nette,
	Elasticsearch\Client;


/**
 * Products repository.
 */
class ProductEsAggrRepository extends Nette\Object implements IProductRepository
{
	/** @var Nette\Database\Context */
	private $es;

	public function __construct(Client $es)
	{
		$this->es = $es;
	}

	/**
	 * (non-PHPdoc)
	 * @see \App\Model\IProductRepository::findByCategory()
	 */
	public function findByCategory($categoryId, array $filters = [])
	{

		$query = [
			'index' => 'eshop',
			'type' => 'product',
			'body' => [
				'query' => [
					'filtered' => [
						'filter' => [
							'term' => [
								'category_id' => $categoryId
							]
						]
					]
				],
				"aggs" => [
					"price" => [
						"histogram" => [
							"field" => "price",
							"interval" => 5000
						]
					],
					"brand" => [
						"terms" => [
							"field" => "brand_id",
							"size" => 10
						]
					]
				]
			]
		];

		// add limit
		if (isset($filters['limit'])) {
			$query['size'] = $filters['limit'];
			if (isset($filters['offset'])) {
				$query['from'] = $filters['offset'];
			}
		}

		// add filters
		$allowedFilters = [
			'price' => ['field' => 'price', 'type' => 'range'],
			'brand' => ['field' => 'brand_id']
		];
		foreach ($allowedFilters as $filterName => $filterSettings) {
			if (isset($filters[$filterName])) {

				if (!isset($query['body']['query']['filtered']['filter']['and'])) {
					$query['body']['query']['filtered']['filter'] = ['and' => [$query['body']['query']['filtered']['filter']]];
				}

				if (!isset($filterSettings['type'])) {
					$query['body']['query']['filtered']['filter']['and'][] = [
						'term' => [
							$filterSettings['field'] => $filters[$filterName]
						]
					];
				} else if ($filterSettings['type'] == 'range') {
					if (strpos($filters[$filterName], '-') !== FALSE) {
						list($min, $max) = explode('-', $filters[$filterName]);
					} else {
						$min = $filters[$filterName];
						$max = null;
					}
					$query['body']['query']['filtered']['filter']['and'][] = [
						'range' => [
							$filterSettings['field'] => [
								'gte' => $min,
								'lte' => $max
							]
						]
					];
				}
			}
		}

		$result = $this->es->search($query);
		$return = [
			'total' => $result['hits']['total'],
			'products' => [],
			'facets' => $result['aggregations']
		];


		foreach ($result['hits']['hits'] as $product) {
			$return['products'][] = $product['_source'];
		}

		return $return;
	}

	public function search($keyword, array $filters = [])
	{
		$query = [
			'index' => 'eshop',
			'type' => 'product',
			'body' => [
				'query' => [
					'term' => [
						'title' => $keyword
					]
				]
			]
		];

		// add limit
		if (isset($filters['limit'])) {
			$query['size'] = $filters['limit'];
			if (isset($filters['offset'])) {
				$query['from'] = $filters['offset'];
			}
		}

		$result = $this->es->search($query);
		$return = [
			'total' => $result['hits']['total'],
			'products' => []
		];


		foreach ($result['hits']['hits'] as $product) {
			$return['products'][] = $product['_source'];
		}

		return $return;
	}



}
