<p style="font-size:12.5px;color:var(--mute);margin:0 0 16px;line-height:1.7">
    بعد حذف الحساب، ستُمحى جميع بياناتك ومواردك نهائياً. يُرجى تنزيل أي بيانات ترغب بالاحتفاظ بها قبل المتابعة.
</p>

<button type="button"
        class="mz-btn"
        style="background:rgba(224,85,85,.15);color:#e05555;border-color:rgba(224,85,85,.35)"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
    حذف الحساب
</button>

<x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
    <form method="post" action="{{ route('profile.destroy') }}" style="padding:24px">
        @csrf
        @method('delete')

        <h3 style="font-size:16px;font-weight:700;color:var(--ink);margin:0 0 8px">
            هل أنت متأكد من حذف حسابك؟
        </h3>

        <p style="font-size:12.5px;color:var(--mute);margin:0 0 16px;line-height:1.7">
            بعد حذف الحساب، ستُمحى جميع البيانات نهائياً. أدخل كلمة المرور لتأكيد رغبتك في الحذف الدائم.
        </p>

        <div class="mz-form-group">
            <label for="password" class="mz-flabel" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">كلمة المرور</label>
            <input id="password" name="password" type="password"
                   class="mz-inp" dir="ltr" placeholder="كلمة المرور">
            @foreach ($errors->userDeletion->get('password') as $message)
                <div class="mz-form-error">{{ $message }}</div>
            @endforeach
        </div>

        <div class="mz-form-actions" style="justify-content:flex-end;gap:8px">
            <button type="button" class="mz-btn" x-on:click="$dispatch('close')">إلغاء</button>
            <button type="submit" class="mz-btn"
                    style="background:#e05555;color:#fff;border-color:#e05555">
                تأكيد الحذف
            </button>
        </div>
    </form>
</x-modal>
