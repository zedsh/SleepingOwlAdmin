<?php

namespace SleepingOwl\Admin\Display\Extension;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use SleepingOwl\Admin\Contracts\ColumnInterface;
use SleepingOwl\Admin\Contracts\Initializable;
use SleepingOwl\Admin\Display\Column\Control;

class Columns extends Extension implements Initializable, Renderable
{
    /**
     * @var ColumnInterface[]|Collection
     */
    protected $columns;

    /**
     * @var bool
     */
    protected $controlActive = true;

    /**
     * @var string
     */
    protected $view = 'display.extensions.columns';

    /**
     * @var bool
     */
    protected $initialize = false;

    /**
     * @var Control
     */
    protected $controlColumn;

    public function __construct()
    {
        $this->columns = new Collection();

        $this->setControlColumn(app('sleeping_owl.table.column')->control());
    }

    /**
     * @param ColumnInterface $controlColumn
     *
     * @return $this
     */
    public function setControlColumn(ColumnInterface $controlColumn)
    {
        $this->controlColumn = $controlColumn;

        return $this;
    }

    /**
     * @return Control
     */
    public function getControlColumn()
    {
        return $this->controlColumn;
    }

    /**
     * @return bool
     */
    public function isControlActive()
    {
        return $this->controlActive;
    }

    /**
     * @return $this
     */
    public function enableControls()
    {
        $this->controlActive = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableControls()
    {
        $this->controlActive = false;

        if ($this->isInitialize()) {
            $this->columns = $this->columns->filter(function ($column) {
                $class = get_class($this->getControlColumn());

                return ! ($column instanceof $class);
            });
        }

        return $this;
    }

    /**
     * @return Collection|\SleepingOwl\Admin\Contracts\ColumnInterface[]
     */
    public function all()
    {
        return $this->columns;
    }

    /**
     * @param $columns
     *
     * @return \SleepingOwl\Admin\Contracts\DisplayInterface
     */
    public function set($columns)
    {
        if (! is_array($columns)) {
            $columns = func_get_args();
        }

        foreach ($columns as $column) {
            $this->push($column);
        }

        return $this->getDisplay();
    }

    /**
     * @param ColumnInterface $column
     *
     * @return $this
     */
    public function push(ColumnInterface $column)
    {
        $this->columns->push($column);

        return $this;
    }

    /**
     * @return string|\Illuminate\View\View
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @param string|\Illuminate\View\View $view
     *
     * @return $this
     */
    public function setView($view)
    {
        $this->view = $view;

        return $this;
    }

    /**
     * @return bool
     */
    public function isInitialize()
    {
        return $this->initialize;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'columns' => $this->all(),
            'attributes' => $this->getDisplay()->htmlAttributesToString(),
        ];
    }

    public function initialize()
    {
        $this->all()->each(function (ColumnInterface $column) {
            $column->initialize();
        });

        if ($this->isControlActive()) {
            $this->push($this->getControlColumn());
        }

        $this->initialize = true;
    }

    /**
     * Get the evaluated contents of the object.
     *
     * @return string
     */
    public function render()
    {
        $params = $this->toArray();
        $params['collection'] = $this->getDisplay()->getCollection();

        return $this->getDisplay()->template()->view($this->getView(), $params)->render();
    }
}
