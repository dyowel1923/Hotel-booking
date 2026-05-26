<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  PALAWAN TOURISM PORTAL — Models, Routes & Controllers
 *  Laravel 11 | PHP 8.2
 * ═══════════════════════════════════════════════════════════════════
 */

// ════════════════════════════════════════════════════════
//  SECTION 1: ELOQUENT MODELS
// ════════════════════════════════════════════════════════

// ── app/Models/User.php ──────────────────────────────────
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password',
        'phone', 'nationality', 'address', 'profile_photo',
        'role', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ── Accessors ──
    public function getFullNameAttribute(): string {
        return "{$this->first_name} {$this->last_name}";
    }

    // ── Role helpers ──
    public function isAdmin(): bool        { return $this->role === 'admin'; }
    public function isTourist(): bool      { return $this->role === 'tourist'; }
    public function isEstablishment(): bool { return $this->role === 'establishment'; }

    // ── Relationships ──
    public function establishment()  { return $this->hasOne(Establishment::class); }
    public function bookings()       { return $this->hasMany(Booking::class, 'tourist_id'); }
    public function reviews()        { return $this->hasMany(Review::class, 'tourist_id'); }
    public function wishlists()      { return $this->hasMany(Wishlist::class, 'tourist_id'); }
    public function sentMessages()   { return $this->hasMany(Message::class, 'sender_id'); }
    public function receivedMessages() { return $this->hasMany(Message::class, 'receiver_id'); }
}

// ── app/Models/Establishment.php ─────────────────────────
class Establishment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'slug', 'type', 'description', 'short_desc',
        'address', 'municipality', 'barangay', 'latitude', 'longitude',
        'phone', 'email', 'website', 'cover_photo', 'logo',
        'accreditation_no', 'amenities', 'tags', 'star_rating',
        'status', 'is_featured',
    ];

    protected $casts = [
        'amenities'   => 'array',
        'tags'        => 'array',
        'is_featured' => 'boolean',
        'approved_at' => 'datetime',
        'avg_rating'  => 'float',
    ];

    // ── Relationships ──
    public function owner()     { return $this->belongsTo(User::class, 'user_id'); }
    public function rooms()     { return $this->hasMany(Room::class); }
    public function services()  { return $this->hasMany(Service::class); }
    public function bookings()  { return $this->hasMany(Booking::class); }
    public function reviews()   { return $this->hasMany(Review::class); }
    public function photos()    { return $this->hasMany(EstablishmentPhoto::class)->orderBy('sort_order'); }
    public function wishlists() { return $this->hasMany(Wishlist::class); }

    // ── Scopes ──
    public function scopeApproved($q)          { return $q->where('status', 'approved'); }
    public function scopeFeatured($q)           { return $q->where('is_featured', true); }
    public function scopeByMunicipality($q, $m) { return $q->where('municipality', $m); }
    public function scopeByType($q, $t)         { return $q->where('type', $t); }

    // ── Helpers ──
    public function isApproved(): bool { return $this->status === 'approved'; }

    public function updateRating(): void {
        $avg = $this->reviews()->where('is_approved', true)->avg('rating');
        $this->update([
            'avg_rating'    => round($avg ?? 0, 2),
            'total_reviews' => $this->reviews()->where('is_approved', true)->count(),
        ]);
    }
}

// ── app/Models/Booking.php ───────────────────────────────
class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_ref', 'tourist_id', 'establishment_id', 'room_id',
        'booking_type', 'check_in_date', 'check_out_date', 'event_date',
        'num_nights', 'num_guests', 'num_children', 'special_requests',
        'subtotal', 'tourism_fee', 'service_fee', 'discount_amount',
        'promo_code', 'total_amount', 'status', 'qr_code_path', 'qr_token',
        'source', 'admin_notes',
    ];

    protected $casts = [
        'check_in_date'  => 'date',
        'check_out_date' => 'date',
        'event_date'     => 'datetime',
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
        'confirmed_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    // ── Boot: auto-generate booking_ref ──
    protected static function boot(): void {
        parent::boot();
        static::creating(function ($booking) {
            $booking->booking_ref = static::generateRef();
        });
    }

    public static function generateRef(): string {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('PLW-%d-%05d', $year, $count);
    }

    // ── Relationships ──
    public function tourist()       { return $this->belongsTo(User::class, 'tourist_id'); }
    public function establishment() { return $this->belongsTo(Establishment::class); }
    public function room()          { return $this->belongsTo(Room::class); }
    public function services()      { return $this->hasMany(BookingService::class); }
    public function payment()       { return $this->hasOne(Payment::class); }
    public function review()        { return $this->hasOne(Review::class); }
    public function messages()      { return $this->hasMany(Message::class); }
    public function feeLog()        { return $this->hasOne(TourismFeeLog::class); }

    // ── Scopes ──
    public function scopePending($q)   { return $q->where('status', 'pending'); }
    public function scopeConfirmed($q) { return $q->where('status', 'confirmed'); }
    public function scopeUpcoming($q)  {
        return $q->whereIn('status', ['pending','confirmed'])
                 ->where('check_in_date', '>=', today());
    }

    // ── Status helpers ──
    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isConfirmed(): bool  { return $this->status === 'confirmed'; }
    public function isCancellable(): bool {
        if (!in_array($this->status, ['pending', 'confirmed'])) return false;
        $deadline = ($this->check_in_date ?? $this->event_date)?->subHours(48);
        return now()->lt($deadline);
    }
}

// ── app/Models/Room.php ──────────────────────────────────
class Room extends Model {
    use HasFactory;
    protected $fillable = [
        'establishment_id','name','room_number','type','description',
        'capacity','bed_count','bed_type','price_per_night','price_weekend',
        'extra_person_fee','is_available','amenities','photos',
        'floor_number','room_size_sqm',
    ];
    protected $casts = ['amenities'=>'array','photos'=>'array','is_available'=>'boolean'];
    public function establishment() { return $this->belongsTo(Establishment::class); }
    public function bookings()      { return $this->hasMany(Booking::class); }
    public function availability()  { return $this->hasMany(RoomAvailability::class); }

    public function isAvailableFor(string $checkIn, string $checkOut): bool {
        return !$this->availability()
            ->whereBetween('blocked_date', [$checkIn, $checkOut])
            ->exists();
    }
}

// ── app/Models/Service.php ───────────────────────────────
class Service extends Model {
    use HasFactory;
    protected $fillable = [
        'establishment_id','name','category','description','inclusions','exclusions',
        'price','price_type','max_capacity','min_booking_pax','duration_hours',
        'available_days','start_time','end_time','photos','requires_confirmation','is_active',
    ];
    protected $casts = ['available_days'=>'array','photos'=>'array','is_active'=>'boolean'];
    public function establishment() { return $this->belongsTo(Establishment::class); }
    public function bookingServices() { return $this->hasMany(BookingService::class); }
    public function schedules()     { return $this->hasMany(AvailabilitySchedule::class); }
}

// ════════════════════════════════════════════════════════
//  SECTION 2: ROUTES  (routes/web.php)
// ════════════════════════════════════════════════════════
use App\Http\Controllers\Tourist\HomeController;
use App\Http\Controllers\Tourist\EstablishmentController as TouristEstablishment;
use App\Http\Controllers\Tourist\BookingController as TouristBooking;
use App\Http\Controllers\Tourist\PaymentController;
use App\Http\Controllers\Tourist\ProfileController as TouristProfile;
use App\Http\Controllers\Tourist\WishlistController;
use App\Http\Controllers\Tourist\ReviewController as TouristReview;
use App\Http\Controllers\Establishment\DashboardController as EstDashboard;
use App\Http\Controllers\Establishment\RoomController;
use App\Http\Controllers\Establishment\ServiceController;
use App\Http\Controllers\Establishment\BookingController as EstBooking;
use App\Http\Controllers\Establishment\AvailabilityController;
use App\Http\Controllers\Establishment\MessageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\EstablishmentController as AdminEst;
use App\Http\Controllers\Admin\TouristSpotController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BookingController as AdminBooking;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Auth\AuthController;

// ── PUBLIC ROUTES ──────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/destinations', [HomeController::class, 'destinations'])->name('destinations');
Route::get('/establishments', [TouristEstablishment::class, 'index'])->name('establishments.index');
Route::get('/establishments/{slug}', [TouristEstablishment::class, 'show'])->name('establishments.show');
Route::get('/spots', [TouristSpotController::class, 'publicIndex'])->name('spots.index');
Route::get('/spots/{slug}', [TouristSpotController::class, 'publicShow'])->name('spots.show');
Route::get('/announcements', [AnnouncementController::class, 'publicIndex'])->name('announcements.index');

// ── AUTH ROUTES ────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/register/establishment', [AuthController::class, 'showEstRegister'])->name('register.establishment');
    Route::post('/register/establishment', [AuthController::class, 'registerEstablishment']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── TOURIST ROUTES ─────────────────────────────────────
Route::middleware(['auth', 'role:tourist'])->prefix('my')->name('tourist.')->group(function () {
    Route::get('/dashboard', [TouristProfile::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [TouristProfile::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [TouristProfile::class, 'update'])->name('profile.update');

    // Bookings
    Route::get('/bookings', [TouristBooking::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{ref}', [TouristBooking::class, 'show'])->name('bookings.show');
    Route::post('/bookings', [TouristBooking::class, 'store'])->name('bookings.store');
    Route::delete('/bookings/{ref}', [TouristBooking::class, 'cancel'])->name('bookings.cancel');

    // Payments
    Route::get('/bookings/{ref}/pay', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/bookings/{ref}/pay', [PaymentController::class, 'initiate'])->name('payment.initiate');
    Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');

    // Wishlist
    Route::post('/wishlist/{establishment}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');

    // Reviews
    Route::get('/reviews/create/{booking}', [TouristReview::class, 'create'])->name('reviews.create');
    Route::post('/reviews/{booking}', [TouristReview::class, 'store'])->name('reviews.store');
});

// ── ESTABLISHMENT ROUTES ───────────────────────────────
Route::middleware(['auth', 'role:establishment', 'establishment.approved'])
    ->prefix('portal')->name('est.')->group(function () {
    Route::get('/dashboard', [EstDashboard::class, 'index'])->name('dashboard');

    // Rooms
    Route::resource('rooms', RoomController::class);
    Route::patch('/rooms/{room}/toggle', [RoomController::class, 'toggleAvailability'])->name('rooms.toggle');

    // Services
    Route::resource('services', ServiceController::class);

    // Availability Calendar
    Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
    Route::post('/availability/block', [AvailabilityController::class, 'block'])->name('availability.block');
    Route::delete('/availability/{id}', [AvailabilityController::class, 'unblock'])->name('availability.unblock');

    // Bookings
    Route::get('/bookings', [EstBooking::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [EstBooking::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/confirm', [EstBooking::class, 'confirm'])->name('bookings.confirm');
    Route::patch('/bookings/{booking}/reject', [EstBooking::class, 'reject'])->name('bookings.reject');
    Route::patch('/bookings/{booking}/checkin', [EstBooking::class, 'checkIn'])->name('bookings.checkin');
    Route::patch('/bookings/{booking}/checkout', [EstBooking::class, 'checkOut'])->name('bookings.checkout');
    Route::get('/bookings/scan-qr', [EstBooking::class, 'scanQr'])->name('bookings.scan');
    Route::post('/bookings/verify-qr', [EstBooking::class, 'verifyQr'])->name('bookings.verify');

    // Messages
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{booking}', [MessageController::class, 'thread'])->name('messages.thread');
    Route::post('/messages/{booking}', [MessageController::class, 'send'])->name('messages.send');

    // Profile
    Route::get('/profile', [EstDashboard::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [EstDashboard::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/photos', [EstDashboard::class, 'uploadPhotos'])->name('profile.photos');
});

// Pending establishment (registered but not yet approved)
Route::middleware(['auth', 'role:establishment'])
    ->get('/portal/pending', [EstDashboard::class, 'pending'])->name('est.pending');

// ── ADMIN ROUTES ───────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // Establishments management
    Route::get('/establishments', [AdminEst::class, 'index'])->name('establishments.index');
    Route::get('/establishments/{establishment}', [AdminEst::class, 'show'])->name('establishments.show');
    Route::patch('/establishments/{establishment}/approve', [AdminEst::class, 'approve'])->name('establishments.approve');
    Route::patch('/establishments/{establishment}/reject', [AdminEst::class, 'reject'])->name('establishments.reject');
    Route::patch('/establishments/{establishment}/suspend', [AdminEst::class, 'suspend'])->name('establishments.suspend');
    Route::patch('/establishments/{establishment}/feature', [AdminEst::class, 'toggleFeatured'])->name('establishments.feature');

    // Tourist Spots
    Route::resource('spots', TouristSpotController::class);

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

    // Bookings (system-wide view)
    Route::get('/bookings', [AdminBooking::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [AdminBooking::class, 'show'])->name('bookings.show');

    // Announcements
    Route::resource('announcements', AnnouncementController::class);

    // Reports & Export
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/bookings/export', [ReportController::class, 'exportBookings'])->name('reports.bookings.export');
    Route::get('/reports/revenue/export', [ReportController::class, 'exportRevenue'])->name('reports.revenue.export');
    Route::get('/reports/tourism-arrivals', [ReportController::class, 'touristArrivals'])->name('reports.arrivals');

    // Settings
    Route::get('/settings', [AdminDashboard::class, 'settings'])->name('settings');
    Route::put('/settings', [AdminDashboard::class, 'updateSettings'])->name('settings.update');
});

// ── API-STYLE AJAX ROUTES ──────────────────────────────
Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/check-availability', [TouristBooking::class, 'checkAvailability']);
    Route::get('/establishments/search', [TouristEstablishment::class, 'search']);
    Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->withoutMiddleware('auth');
});

// ════════════════════════════════════════════════════════
//  SECTION 3: KEY CONTROLLERS
// ════════════════════════════════════════════════════════

// ── app/Http/Controllers/Tourist/BookingController.php ──
class TouristBookingController extends Controller
{
    public function store(BookingRequest $request): RedirectResponse
    {
        // 1. Check availability (room or service slot)
        $this->validateAvailability($request);

        // 2. Calculate pricing
        $pricing = $this->bookingService->calculatePricing($request);

        // 3. Create booking
        $booking = Booking::create([
            'tourist_id'       => auth()->id(),
            'establishment_id' => $request->establishment_id,
            'room_id'          => $request->room_id,
            'booking_type'     => $request->booking_type,
            'check_in_date'    => $request->check_in_date,
            'check_out_date'   => $request->check_out_date,
            'event_date'       => $request->event_date,
            'num_guests'       => $request->num_guests,
            'num_nights'       => $request->num_nights ?? 0,
            'special_requests' => $request->special_requests,
            'subtotal'         => $pricing['subtotal'],
            'tourism_fee'      => $pricing['tourism_fee'],
            'total_amount'     => $pricing['total'],
            'status'           => 'pending',
            'source'           => 'web',
        ]);

        // 4. Attach add-on services
        if ($request->has('services')) {
            foreach ($request->services as $svcId => $qty) {
                $service = Service::findOrFail($svcId);
                $booking->services()->create([
                    'service_id' => $svcId,
                    'quantity'   => $qty,
                    'unit_price' => $service->price,
                    'subtotal'   => $service->price * $qty,
                ]);
            }
        }

        // 5. Block room dates
        if ($booking->room_id && $booking->check_in_date) {
            $this->bookingService->blockRoomDates($booking);
        }

        // 6. Generate QR code
        $this->qrService->generate($booking);

        // 7. Log tourism fee
        TourismFeeLog::create([
            'booking_id'       => $booking->id,
            'establishment_id' => $booking->establishment_id,
            'fee_amount'       => $pricing['tourism_fee'],
            'fee_percentage'   => setting('tourism_fee_percentage', 2.00),
            'status'           => 'collected',
        ]);

        // 8. Send notifications
        $booking->tourist->notify(new BookingReceived($booking));
        $booking->establishment->owner->notify(new NewBookingRequest($booking));

        return redirect()->route('tourist.bookings.show', $booking->booking_ref)
            ->with('success', 'Booking submitted! Reference: ' . $booking->booking_ref);
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $room = Room::findOrFail($request->room_id);
        $available = $room->isAvailableFor($request->check_in, $request->check_out);
        return response()->json(['available' => $available]);
    }
}

// ── app/Http/Controllers/Establishment/BookingController.php ──
class EstablishmentBookingController extends Controller
{
    public function confirm(Booking $booking): RedirectResponse
    {
        abort_if($booking->establishment_id !== auth()->user()->establishment->id, 403);
        $booking->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);
        $booking->tourist->notify(new BookingConfirmed($booking));
        return back()->with('success', 'Booking confirmed! Tourist has been notified.');
    }

    public function verifyQr(Request $request): JsonResponse
    {
        $booking = Booking::where('qr_token', $request->token)->firstOrFail();
        if ($booking->establishment_id !== auth()->user()->establishment->id) {
            return response()->json(['valid' => false, 'message' => 'QR not for this establishment.']);
        }
        if ($booking->status !== 'confirmed') {
            return response()->json(['valid' => false, 'message' => 'Booking status: ' . $booking->status]);
        }
        $booking->update(['status' => 'checked_in', 'checked_in_at' => now()]);
        return response()->json([
            'valid'   => true,
            'booking' => $booking->load('tourist', 'room'),
            'message' => 'Check-in successful!',
        ]);
    }
}

// ── app/Http/Controllers/Admin/DashboardController.php ──
class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_bookings'       => Booking::count(),
            'pending_bookings'     => Booking::pending()->count(),
            'total_establishments' => Establishment::approved()->count(),
            'pending_approvals'    => Establishment::where('status', 'pending')->count(),
            'total_tourists'       => User::where('role', 'tourist')->count(),
            'total_revenue'        => Payment::where('status', 'paid')->sum('amount'),
            'tourism_fees'         => TourismFeeLog::sum('fee_amount'),
            'this_month_bookings'  => Booking::whereMonth('created_at', now()->month)->count(),
        ];

        $recentBookings = Booking::with(['tourist', 'establishment'])
            ->latest()->take(10)->get();

        $pendingEstablishments = Establishment::where('status', 'pending')
            ->with('owner')->latest()->take(5)->get();

        $bookingsByMonth = Booking::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->pluck('count', 'month');

        $topEstablishments = Establishment::approved()
            ->withCount('bookings')
            ->orderByDesc('bookings_count')
            ->take(5)->get();

        return view('admin.dashboard', compact(
            'stats', 'recentBookings', 'pendingEstablishments',
            'bookingsByMonth', 'topEstablishments'
        ));
    }
}

// ── app/Http/Middleware/CheckRole.php ────────────────────
class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!auth()->check() || auth()->user()->role !== $role) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
            abort(403, 'Access denied. You do not have the required role.');
        }
        return $next($request);
    }
}

// ── app/Http/Middleware/EstablishmentApproved.php ────────
class EstablishmentApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if ($user->role === 'establishment') {
            $establishment = $user->establishment;
            if (!$establishment || $establishment->status !== 'approved') {
                return redirect()->route('est.pending')
                    ->with('warning', 'Your establishment is pending approval by the Tourism Department.');
            }
        }
        return $next($request);
    }
}

// ── app/Services/BookingService.php ──────────────────────
class BookingService
{
    public function calculatePricing(Request $request): array
    {
        $subtotal = 0;
        $feePercent = (float) setting('tourism_fee_percentage', 2.00);

        if ($request->room_id) {
            $room      = Room::findOrFail($request->room_id);
            $nights    = $request->num_nights ?? carbon($request->check_in_date)
                ->diffInDays($request->check_out_date);
            $subtotal += $room->price_per_night * $nights;
            // Extra person fee
            $extra     = max(0, ($request->num_guests - $room->capacity));
            $subtotal += $extra * $room->extra_person_fee;
        }

        // Add-on services
        if ($request->has('services')) {
            foreach ($request->services as $svcId => $qty) {
                $service  = Service::findOrFail($svcId);
                $subtotal += $service->price * $qty;
            }
        }

        $tourismFee = round($subtotal * ($feePercent / 100), 2);
        $total      = $subtotal + $tourismFee;

        return compact('subtotal', 'tourismFee', 'total');
    }

    public function blockRoomDates(Booking $booking): void
    {
        $current = $booking->check_in_date->copy();
        while ($current->lt($booking->check_out_date)) {
            RoomAvailability::firstOrCreate(
                ['room_id' => $booking->room_id, 'blocked_date' => $current->toDateString()],
                ['booking_id' => $booking->id, 'reason' => 'booked']
            );
            $current->addDay();
        }
    }

    public function releaseRoomDates(Booking $booking): void
    {
        RoomAvailability::where('booking_id', $booking->id)->delete();
    }
}

// ── app/Services/QrCodeService.php ───────────────────────
// Requires: composer require simplesoftwareio/simple-qrcode
class QrCodeService
{
    public function generate(Booking $booking): void
    {
        $token    = \Str::random(64);
        $qrData   = url('/portal/bookings/verify/' . $token);
        $filename = "qr/{$booking->booking_ref}.png";

        \QrCode::format('png')
            ->size(300)
            ->generate($qrData, storage_path("app/public/{$filename}"));

        $booking->update([
            'qr_token'     => $token,
            'qr_code_path' => $filename,
        ]);
    }
}

// ════════════════════════════════════════════════════════
//  SECTION 4: SEEDERS
// ════════════════════════════════════════════════════════

// ── database/seeders/EstablishmentSeeder.php ────────────
class EstablishmentSeeder extends Seeder
{
    public function run(): void
    {
        $establishments = [
            // RESORTS
            ['name'=>'El Nido Cove Resort',          'type'=>'resort',      'municipality'=>'El Nido',        'price_from'=>4500, 'rating'=>5.0],
            ['name'=>'Nacpan Beach Eco Resort',       'type'=>'resort',      'municipality'=>'El Nido',        'price_from'=>2900, 'rating'=>4.9],
            ['name'=>'Port Barton Sunset Resort',     'type'=>'resort',      'municipality'=>'San Vicente',    'price_from'=>3200, 'rating'=>4.7],
            ['name'=>'Honda Bay Island Resort',       'type'=>'resort',      'municipality'=>'Puerto Princesa','price_from'=>3800, 'rating'=>4.6],
            ['name'=>'Sabang Treehouse Retreat',      'type'=>'resort',      'municipality'=>'Sabang',         'price_from'=>2500, 'rating'=>4.8],
            // HOTELS
            ['name'=>'Coron Bay Hotel & Spa',         'type'=>'hotel',       'municipality'=>'Coron',          'price_from'=>3800, 'rating'=>4.5],
            ['name'=>'Puerto Princesa Garden Inn',    'type'=>'hotel',       'municipality'=>'Puerto Princesa','price_from'=>2200, 'rating'=>4.4],
            ['name'=>'Busuanga Safari Lodge',         'type'=>'hotel',       'municipality'=>'Busuanga',       'price_from'=>2800, 'rating'=>4.6],
            // RESTAURANTS
            ['name'=>'Baywalk Grill & Seafood',       'type'=>'restaurant',  'municipality'=>'Puerto Princesa','price_from'=>600,  'rating'=>5.0],
            ["name'=>'Kalui's Floating Kitchen",     'type'=>'restaurant',  'municipality'=>'Puerto Princesa','price_from'=>850,  'rating'=>4.9],
            // EVENT VENUES
            ['name'=>'Palawan Garden Event Center',   'type'=>'event_venue', 'municipality'=>'Puerto Princesa','price_from'=>25000,'rating'=>4.9],
        ];

        foreach ($establishments as $data) {
            // create user account for each establishment
            $user = User::create([
                'first_name' => 'Manager',
                'last_name'  => $data['name'],
                'email'      => \Str::slug($data['name']) . '@palawantourism.test',
                'password'   => bcrypt('password'),
                'role'       => 'establishment',
            ]);
            Establishment::create([
                'user_id'      => $user->id,
                'name'         => $data['name'],
                'slug'         => \Str::slug($data['name']),
                'type'         => $data['type'],
                'municipality' => $data['municipality'],
                'avg_rating'   => $data['rating'],
                'status'       => 'approved',
                'is_featured'  => true,
                'short_desc'   => "Welcome to {$data['name']} — a premier destination in Palawan.",
                'description'  => "Discover the beauty of Palawan at {$data['name']}...",
                'approved_at'  => now(),
            ]);
        }
    }
}

// ── database/seeders/TouristSpotSeeder.php ───────────────
class TouristSpotSeeder extends Seeder
{
    public function run(): void
    {
        $spots = [
            [
                'name'         => 'Puerto Princesa Subterranean River National Park',
                'slug'         => 'puerto-princesa-underground-river',
                'category'     => 'cave',
                'municipality' => 'Puerto Princesa',
                'entrance_fee' => 150,
                'requires_booking' => true,
                'daily_visitor_limit' => 800,
                'is_featured'  => true,
                'description'  => 'UNESCO World Heritage Site and one of the New 7 Wonders of Nature. An 8.2km navigable underground river featuring stunning cave formations and diverse wildlife.',
                'operating_hours' => '8:00 AM – 4:00 PM',
                'best_time_to_visit' => 'November to May (dry season)',
            ],
            [
                'name'         => 'El Nido Islands & Lagoons',
                'slug'         => 'el-nido-islands-lagoons',
                'category'     => 'island',
                'municipality' => 'El Nido',
                'entrance_fee' => 200,
                'requires_booking' => true,
                'is_featured'  => true,
                'description'  => 'Dramatic limestone karst scenery, crystal-clear lagoons, and pristine beaches. Features Big Lagoon, Small Lagoon, Secret Lagoon, and Seven Commandos Beach.',
            ],
            [
                'name'         => 'Coron Island Lagoons & Wrecks',
                'slug'         => 'coron-island-lagoons',
                'category'     => 'island',
                'municipality' => 'Coron',
                'entrance_fee' => 250,
                'requires_booking' => false,
                'is_featured'  => true,
                'description'  => 'Home to Kayangan Lake, Twin Lagoon, and legendary WWII Japanese shipwrecks. World-class wreck diving and snorkeling.',
            ],
            [
                'name'         => 'Honda Bay Island Hopping',
                'slug'         => 'honda-bay-island-hopping',
                'category'     => 'island',
                'municipality' => 'Puerto Princesa',
                'entrance_fee' => 50,
                'is_featured'  => true,
                'description'  => 'Island hopping tour covering Snake Island (the tidal sandbar), Luli Island, Starfish Island, and Cowrie Island with crystal-clear waters.',
            ],
            [
                'name'         => 'Tubbataha Reef National Marine Park',
                'slug'         => 'tubbataha-reef',
                'category'     => 'reef',
                'municipality' => 'Cagayancillo',
                'entrance_fee' => 500,
                'requires_booking' => true,
                'description'  => 'UNESCO World Heritage Site – one of the best dive sites in the world. Only accessible by liveaboard during March–June season.',
                'best_time_to_visit' => 'March to June (open season)',
            ],
            [
                'name'         => 'Nacpan Twin Beach',
                'slug'         => 'nacpan-twin-beach',
                'category'     => 'beach',
                'municipality' => 'El Nido',
                'entrance_fee' => 0,
                'is_featured'  => true,
                'description'  => 'A stunning 4km stretch of white sand beach with two beaches facing different directions. One of the most beautiful undeveloped beaches in the Philippines.',
            ],
            [
                'name'         => 'Tabon Caves Archaeological Site',
                'slug'         => 'tabon-caves',
                'category'     => 'cave',
                'municipality' => 'Quezon',
                'entrance_fee' => 100,
                'description'  => 'Archaeological site where the Tabon Man fossil was found. Over 200 caves on Lipuun Point, considered the cradle of Philippine civilization.',
                'best_time_to_visit' => 'October to May',
            ],
            [
                'name'         => 'Balabac Island Chain',
                'slug'         => 'balabac-islands',
                'category'     => 'island',
                'municipality' => 'Balabac',
                'entrance_fee' => 200,
                'description'  => 'Remote island paradise at the southernmost tip of Palawan. Features Onuk Island, Candaraman Island, and the incredibly clear turquoise waters of Bugsuk.',
            ],
        ];

        foreach ($spots as $spot) {
            TouristSpot::create($spot);
        }
    }
}
