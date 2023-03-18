<?php

namespace ProcessWire;

/**
 * Template Access log
 *
 * Logs changes made to template roles and related access settings
 *
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class TemplateAccessLog extends WireData implements Module {

	public static function getModuleInfo() {
		return [
			'title' => 'Template Access log',
			'version' => '0.1.0',
			'summary' => 'Logs changes made to template roles and related access settings',
			'autoload' => true,
			'singular' => true,
			'icon' => 'key',
		];
	}

	public function init() {
		$this->addHookBefore('Templates::save', $this, 'logChanges');
	}

	protected function logChanges(HookEvent $event) {

		$item = $event->arguments(0);

		$data = $this->database
			->query('SELECT data FROM templates WHERE id = ' . (int) $item->id)
			->fetchColumn();
		$data = json_decode($data, true);

		$permissions = [
			'view' => [
				'prop' => 'roles',
				'data' => $item->roles->explode('id'),
			],
			'edit' => [
				'prop' => 'editRoles',
				'data' => $item->editRoles,
			],
			'add' => [
				'prop' => 'addRoles',
				'data' => $item->addRoles,
			],
			'create' => [
				'prop' => 'createRoles',
				'data' => $item->createRoles,
			],
		];

		$permissions_changed = $item->id < 1 ? array_filter(array_map(function ($permission) {
			return array_filter($permission['data']);
		}, $permissions)) : [];
		if (empty($permissions_changed)) {
			foreach ($permissions as $permission => $permission_data) {
				if (($data[$permission_data['prop']] ?? []) != $permission_data['data']) {
					$permissions_changed[$permission] = $this->getArrayDiff(
						$data[$permission_data['prop']] ?? [],
						$permission_data['data']
					);
				}
			}
		}

		if ($item->isChanged('useRoles') || !empty($permissions_changed)) {
			$this->wire->log->save(
				'template_access_log',
				json_encode([
					'template' => $item->name,
					'template_id' => $item->id,
					'use_roles' => $item->useRoles,
					'permissions' => array_map(function ($permission) {
						return $permission['data'];
					}, $permissions),
					'permissions_changed' => $permissions_changed,
				])
			);
		}
	}

	protected function getArrayDiff($old_array, $new_array): array {
		return array_merge(array_diff($new_array, $old_array), array_map(function($removal) {
			return -$removal;
		}, array_diff($old_array, $new_array)));
	}
}
