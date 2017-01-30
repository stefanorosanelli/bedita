<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2017 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\API\Controller;

use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\ConflictException;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\Query;
use Cake\Routing\Router;

/**
 * Controller for `/properties` endpoint.
 *
 * @since 4.0.0
 *
 * @property \BEdita\Core\Model\Table\PropertiesTable $Properties
 */
class PropertiesController extends ResourcesController
{

    /**
     * {@inheritDoc}
     */
    public $modelClass = 'Properties';

    /**
     * {@inheritDoc}
     */
    protected $_defaultConfig = [
        'allowedAssociations' => [
            'object_types' => ['object_types'],
        ],
    ];

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        parent::initialize();

        if (isset($this->JsonApi) && $this->request->param('action') != 'relationships') {
            $this->JsonApi->config('resourceTypes', ['properties']);
        }
    }

    /**
     * Paginated Properties list.
     *
     * @return void
     */
    public function index()
    {
        $query = $this->Properties->find('all');

        if ($objectTypeId = $this->request->param('object_type_id')) {
            $query = $query->innerJoinWith('ObjectTypes', function (Query $query) use ($objectTypeId) {
                return $query->where(['ObjectTypes.id' => $objectTypeId]);
            });
        }

        $Properties = $this->paginate($query);

        $this->set(compact('Properties'));
        $this->set('_serialize', ['Properties']);
    }

    /**
     * Get property's data.
     *
     * @param int $id Property ID.
     * @return void
     */
    public function view($id)
    {
        $property = $this->Properties->get($id);

        $this->set(compact('property'));
        $this->set('_serialize', ['property']);
    }

    /**
     * Add a new Property.
     *
     * @return void
     * @throws \Cake\Network\Exception\BadRequestException Throws an exception if submitted data is invalid.
     */
    public function add()
    {
        $this->request->allowMethod('post');

        $property = $this->Properties->newEntity($this->request->data);
        if (!$this->Properties->save($property)) {
            $this->log('Property add failed ' . json_encode($property->errors()), 'error');
            throw new BadRequestException(['title' => 'Invalid data', 'detail' => [$property->errors()]]);
        }

        $this->response->statusCode(201);
        $this->response->header('Location', Router::url(['_name' => 'api:properties:view', $property->id], true));

        $this->set(compact('property'));
        $this->set('_serialize', ['property']);
    }

    /**
     * Edit an existing Property.
     *
     * @param int $id Property ID.
     * @return void
     * @throws \Cake\Network\Exception\ConflictException Throws an exception if Property ID in the payload doesn't match
     *      the Property ID in the URL.
     * @throws \Cake\Network\Exception\NotFoundException Throws an exception if specified Property could not be found.
     * @throws \Cake\Network\Exception\BadRequestException Throws an exception if submitted data is invalid.
     */
    public function edit($id)
    {
        $this->request->allowMethod('patch');

        if ($this->request->data('id') != $id) {
            throw new ConflictException('IDs don\'t match');
        }

        $property = $this->Properties->get($id);
        $property = $this->Properties->patchEntity($property, $this->request->data);
        if (!$this->Properties->save($property)) {
            $this->log('Property edit failed ' . json_encode($property->errors()), 'error');
            throw new BadRequestException(['title' => 'Invalid data', 'detail' => [$property->errors()]]);
        }

        $this->set(compact('property'));
        $this->set('_serialize', ['property']);
    }

    /**
     * Delete an existing Property.
     *
     * @param int $id Property ID.
     * @return void
     * @throws \Cake\Network\Exception\InternalErrorException Throws an exception if an error occurs during deletion.
     */
    public function delete($id)
    {
        $this->request->allowMethod('delete');

        $property = $this->Properties->get($id);
        if (!$this->Properties->delete($property)) {
            throw new InternalErrorException('Could not delete Property');
        }

        $this->noContentResponse();
    }
}