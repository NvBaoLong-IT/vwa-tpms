<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoginLogController extends Controller
{
    public const MODULES = ['authentication' => 'Đăng nhập', 'account' => 'Tài khoản cá nhân', 'curriculum' => 'Chương trình & học phần', 'academic' => 'Học vụ', 'iso' => 'Rà soát chất lượng', 'diploma' => 'Văn bằng', 'reports' => 'Báo cáo', 'users' => 'Quản lý người dùng'];

    public const ACTIONS = ['login' => 'Đăng nhập', 'logout' => 'Đăng xuất', 'create' => 'Tạo / gửi yêu cầu', 'update' => 'Cập nhật', 'delete' => 'Xóa', 'import' => 'Nhập dữ liệu', 'export' => 'Xuất dữ liệu', 'download' => 'Tải hồ sơ', 'approve' => 'Phê duyệt', 'reject' => 'Từ chối', 'close' => 'Hoàn tất', 'cancel' => 'Hủy', 'publish' => 'Công bố', 'scan' => 'Quét cảnh báo', 'upload' => 'Bổ sung hồ sơ', 'read' => 'Đánh dấu đã đọc', 'password' => 'Cập nhật mật khẩu', 'attach' => 'Gắn học phần', 'detach' => 'Gỡ học phần', 'assign' => 'Phân công'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'successful' => ['nullable', 'in:0,1'],
            'module' => ['nullable', 'in:'.implode(',', array_keys(self::MODULES))],
            'action' => ['nullable', 'in:'.implode(',', array_keys(self::ACTIONS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $activity = DB::table('activity_logs')->select(['id', 'user_id', 'actor_name', 'module', 'action', 'route_name', 'subjects', 'successful', 'status_code', 'ip_address', 'created_at as logged_at'])->selectRaw("NULL as identifier, 'activity' as source");
        $login = DB::table('login_logs')->leftJoin('users', 'users.id', '=', 'login_logs.user_id')
            ->select(['login_logs.id', 'login_logs.user_id', 'users.name as actor_name'])
            ->selectRaw("'authentication' as module, 'login' as action, NULL as route_name, NULL as subjects")
            ->addSelect('login_logs.successful')->selectRaw('NULL as status_code')
            ->addSelect(['login_logs.ip_address', 'login_logs.logged_at', 'login_logs.identifier'])->selectRaw("'login' as source");
        $query = DB::query()->fromSub($activity->unionAll($login), 'events');
        foreach (['user_id', 'successful', 'module', 'action'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($key, $filters[$key]);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('logged_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('logged_at', '<', Carbon::parse($filters['to'])->addDay()->toDateString());
        }

        return view('users.login-logs', [
            'logs' => $query->orderByDesc('logged_at')->orderByDesc('id')->paginate(30)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name']), 'modules' => self::MODULES, 'actions' => self::ACTIONS,
        ]);
    }
}
