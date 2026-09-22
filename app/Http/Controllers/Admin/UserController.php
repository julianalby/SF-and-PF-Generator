<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('id')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        // Only validated fields; the password is hashed (Argon2id) by the model cast.
        $user = User::create($request->validated());

        return redirect()->route('admin.users.index')
            ->with('status', "User {$user->username} was created.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user]);
    }

    public function updatePassword(UpdateUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill(['password' => $request->validated('password')])->save(); // hashed by the cast

        return redirect()->route('admin.users.index')
            ->with('status', "Password for {$user->username} was reset.");
    }
}
