<?php

namespace YourVendor\Vespa;

class VespaQueryBuilder
{
    protected $selectFields = '*';
    protected $source = '';
    protected $whereClauses = [];
    protected $orderBy = '';
    protected $limit = '';
    protected $offset = '';
    protected $groupBy = '';
    protected $having = '';
    protected $joins = [];
    protected $orWhereClauses = [];

    // Select specific fields
    public function select($fields)
    {
        if (is_array($fields)) {
            $this->selectFields = implode(', ', $fields);
        } else {
            $this->selectFields = $fields;
        }
        return $this;
    }

    // Specify the source (table) to query from
    public function from($source)
    {
        $this->source = $source;
        return $this;
    }

    // Add where conditions
    public function where($field, $operator = '=', $value = null)
    {
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
        }
        $this->whereClauses[] = "{$field} {$operator} '{$value}'";
        return $this;
    }

    // Add where conditions with OR logic
    public function orWhere($field, $operator = '=', $value = null)
    {
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
        }
        $this->orWhereClauses[] = "{$field} {$operator} '{$value}'";
        return $this;
    }

    // Add nested where conditions
    public function whereNested(callable $callback)
    {
        $nestedQuery = new self();
        $callback($nestedQuery);
        $this->whereClauses[] = '(' . implode(' and ', $nestedQuery->whereClauses) . ')';
        return $this;
    }

    // Add multiple where conditions
    public function whereIn($field, array $values)
    {
        $valueString = implode("', '", $values);
        $this->whereClauses[] = "{$field} IN ('{$valueString}')";
        return $this;
    }

    // Add an order by clause
    public function orderBy($field, $direction = 'asc')
    {
        $this->orderBy = "order by {$field} {$direction}";
        return $this;
    }

    // Set the limit
    public function limit($limit)
    {
        $this->limit = "limit {$limit}";
        return $this;
    }

    // Set the offset
    public function offset($offset)
    {
        $this->offset = "offset {$offset}";
        return $this;
    }

    // Add a group by clause
    public function groupBy($fields)
    {
        if (is_array($fields)) {
            $this->groupBy = 'group by ' . implode(', ', $fields);
        } else {
            $this->groupBy = "group by {$fields}";
        }
        return $this;
    }

    // Add a having clause
    public function having($field, $operator = '=', $value = null)
    {
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
        }
        $this->having = "having {$field} {$operator} '{$value}'";
        return $this;
    }

    // Add a join clause (simulated for Vespa)
    public function join($source, $on, $type = 'inner')
    {
        $this->joins[] = "{$type} join {$source} on {$on}";
        return $this;
    }

    // Add a full-text search condition
    public function match($field, $value)
    {
        $this->whereClauses[] = "match({$field}, '{$value}')";
        return $this;
    }

    // Generate the final YQL query string
    public function getQuery()
    {
        $query = "select {$this->selectFields} from {$this->source}";

        if (!empty($this->joins)) {
            $query .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->whereClauses)) {
            $query .= " where " . implode(' and ', $this->whereClauses);
        }

        if (!empty($this->orWhereClauses)) {
            $query .= (!empty($this->whereClauses) ? ' or ' : ' where ') . implode(' or ', $this->orWhereClauses);
        }

        if (!empty($this->groupBy)) {
            $query .= " {$this->groupBy}";
        }

        if (!empty($this->having)) {
            $query .= " {$this->having}";
        }

        if (!empty($this->orderBy)) {
            $query .= " {$this->orderBy}";
        }

        if (!empty($this->limit)) {
            $query .= " {$this->limit}";
        }

        if (!empty($this->offset)) {
            $query .= " {$this->offset}";
        }

        return $query;
    }

    // Convenience method to get the query as a string when the object is cast to a string
    public function __toString()
    {
        return $this->getQuery();
    }

    // Execute a raw YQL query
    public function raw($query)
    {
        return $query;
    }

    // Clear the builder state
    public function clear()
    {
        $this->selectFields = '*';
        $this->source = '';
        $this->whereClauses = [];
        $this->orderBy = '';
        $this->limit = '';
        $this->offset = '';
        $this->groupBy = '';
        $this->having = '';
        $this->joins = [];
        $this->orWhereClauses = [];
        return $this;
    }
}
