<?php

namespace App\Presentation;

use App\Master\MenuStore;

final class AdminLayoutPresenter
{
    public function __construct(private readonly MenuStore $menus)
    {
    }

    /**
     * @param array<string, mixed> $session
     *
     * @return array<string, mixed>
     */
    public function present(array $session, string $title, string $content, string $profile = 'admin'): array
    {
        $isLoggedIn = ($session['isLoggedIn'] ?? false) === true;
        $groupId = (int) ($session['GroupID'] ?? 0);
        $branchId = ($session['BranchID'] ?? null) === null ? null : (int) $session['BranchID'];
        $showBranchAutocomplete = $isLoggedIn && $groupId <= 3;
        $branchOptions = [];
        if ($showBranchAutocomplete) {
            foreach ($this->menus->branches() as $branch) {
                $branchOptions[] = [
                    'label' => $branch['name'],
                    'value' => base_url('ReportTrackingListing/0/' . $branch['id']),
                ];
            }
        }

        return [
            'pageTitle' => $title,
            'title' => $title,
            'content' => $content,
            'isLoggedIn' => $isLoggedIn,
            'name' => (string) ($session['name'] ?? ''),
            'role_text' => (string) ($session['roleText'] ?? ''),
            'last_login' => (string) ($session['lastLogin'] ?? ''),
            'GroupID' => $groupId,
            'BranchID' => $branchId,
            'BranchName' => $isLoggedIn ? $this->menus->branchName($branchId) : '',
            'menuItems' => $isLoggedIn ? $this->menus->visible($groupId, $branchId) : [],
            'showBranchAutocomplete' => $showBranchAutocomplete,
            'branchOptions' => $branchOptions,
            'layoutProfile' => $profile,
        ];
    }
}
