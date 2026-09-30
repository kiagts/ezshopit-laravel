<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $r)
    {
        $prefix = $r->user()->isCustomer() ? 'account' : $r->user()->role->value;

        return view('notifications.preferences', ['prefix' => $prefix, 'categories' => NotificationCategory::cases(), 'preferences' => DB::table('notification_preferences')->where('user_id', $r->user()->id)->get()->keyBy('category')]);
    }

    public function update(Request $r)
    {
        $data = $r->validate(['locale' => ['required', Rule::in(['en', 'ur', 'ar'])], 'preferences' => 'sometimes|array:'.implode(',', array_column(NotificationCategory::cases(), 'value')), 'preferences.*' => 'array:database_enabled,email_enabled', 'preferences.*.database_enabled' => 'required|boolean', 'preferences.*.email_enabled' => 'required|boolean']);
        DB::transaction(function () use ($r, $data) {
            foreach ($data['preferences'] ?? [] as $category => $values) {
                $required = NotificationCategory::from($category)->required();
                DB::table('notification_preferences')->updateOrInsert(['user_id' => $r->user()->id, 'category' => $category], ['database_enabled' => $required ? true : $values['database_enabled'], 'email_enabled' => $required ? true : $values['email_enabled'], 'created_at' => now(), 'updated_at' => now()]);
            }$r->user()->forceFill(['notification_locale' => $data['locale']])->save();
        });

        return back()->with('status', __('notifications.saved'));
    }
}
