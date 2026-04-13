<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * AdminController
 * ───────────────
 * SuperAdmin-only panel for managing organizations and users.
 */
class AdminController extends Controller
{
    public function __construct()
    {
        // All methods require SuperAdmin
    }

    // ─── Organizations ───

    public function organizations(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $orgs = Organization::withCount('users')
            ->orderBy('name_ar')
            ->get();

        return view('admin.organizations', compact('orgs'));
    }

    public function createOrganization(Request $request)
    {
        $this->ensureSuperAdmin($request);
        return view('admin.create-organization');
    }

    public function storeOrganization(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'name_ar'       => 'required|string|max:255',
            'name_en'       => 'nullable|string|max:255',
            'domain'        => 'required|string|max:100|unique:organizations,domain',
            'phone'         => 'nullable|string|max:50',
            'email'         => 'nullable|email|max:200',
            'website'       => 'nullable|string|max:300',
            'address'       => 'nullable|string|max:500',
            // Admin user for this org
            'admin_name'    => 'required|string|max:255',
            'admin_email'   => 'required|email|max:255|unique:users,email',
            'admin_password' => ['required', Password::min(8)],
            'admin_role'    => 'required|in:' . implode(',', array_column(UserRole::cases(), 'value')),
        ]);

        $org = Organization::create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'domain'  => $data['domain'],
            'phone'   => $data['phone'] ?? null,
            'email'   => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        User::create([
            'name'     => $data['admin_name'],
            'email'    => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'org_id'   => $org->id,
            'role'     => $data['admin_role'],
        ]);

        return redirect()->route('admin.organizations')
            ->with('success', "تم إنشاء جهة \"{$org->name_ar}\" مع حساب المسؤول.");
    }

    // ─── Users ───

    public function users(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $query = User::with('organization')->orderBy('name');

        if ($orgId = $request->query('org')) {
            $query->where('org_id', $orgId);
        }

        $users = $query->paginate(30)->withQueryString();
        $orgs = Organization::orderBy('name_ar')->pluck('name_ar', 'id');
        $roles = UserRole::options();

        return view('admin.users', compact('users', 'orgs', 'roles'));
    }

    public function createUser(Request $request)
    {
        $this->ensureSuperAdmin($request);
        $orgs = Organization::orderBy('name_ar')->pluck('name_ar', 'id');
        $roles = UserRole::cases();
        return view('admin.create-user', compact('orgs', 'roles'));
    }

    public function storeUser(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => ['required', Password::min(8)],
            'org_id'   => 'required|exists:organizations,id',
            'role'     => 'required|in:' . implode(',', array_column(UserRole::cases(), 'value')),
        ]);

        User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'org_id'   => $data['org_id'],
            'role'     => $data['role'],
        ]);

        return redirect()->route('admin.users')
            ->with('success', "تم إنشاء المستخدم \"{$data['name']}\".");
    }

    public function updateUserRole(Request $request, User $user)
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'role' => 'required|in:' . implode(',', array_column(UserRole::cases(), 'value')),
        ]);

        $user->update(['role' => $data['role']]);

        return back()->with('success', "تم تحديث صلاحية \"{$user->name}\" إلى " . UserRole::from($data['role'])->label());
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole(UserRole::SuperAdmin), 403, 'هذه الصفحة للمدير العام فقط.');
    }
}
