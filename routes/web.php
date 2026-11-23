<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredBoxController;
use App\Http\Controllers\BoxLogoController;
use App\Http\Controllers\BoxSettingsController;
use App\Http\Controllers\BoxTvController;
use App\Http\Controllers\BoxTvSettingsController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoadLibraryController;
use App\Http\Controllers\ModalityController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SessionStateController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutCopyController;
use App\Http\Controllers\WorkoutPreviewController;
use App\Http\Controllers\WorkoutSessionController;
use App\Http\Controllers\WorkoutStatusController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\ProtectBoxTvResponse;
use App\Http\Middleware\ResolveBoxTv;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? to_route('workspace') : to_route('login'))->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [RegisteredBoxController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredBoxController::class, 'store'])->middleware('throttle:registration')->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('tv')->name('box.tv.')->withoutMiddleware('web')
    ->middleware(ProtectBoxTvResponse::class)->group(function (): void {
        Route::get('/', [BoxTvController::class, 'open'])->middleware('throttle:box-tv-open')->name('open');
        Route::get('/state', [BoxTvController::class, 'state'])->middleware(['throttle:box-tv-state', ResolveBoxTv::class])->name('state');
        Route::get('/logo', [BoxTvController::class, 'logo'])->middleware(['throttle:box-tv-logo', ResolveBoxTv::class])->name('logo');
    });

Route::middleware(['auth', 'box'])->group(function (): void {
    Route::get('/workspace', WorkspaceController::class)->name('workspace');
    Route::get('/manage', DashboardController::class)->name('manage.dashboard');
    Route::get('/coach', [ClassroomController::class, 'index'])->name('coach.today');
    Route::get('/coach/workouts/{workout}/sessions', [ClassroomController::class, 'history'])->name('coach.history');
    Route::post('/coach/workouts/{workout}/prepare', [ClassroomController::class, 'prepare'])->name('coach.prepare');
    Route::get('/coach/sessions/{session}/ready', [ClassroomController::class, 'ready'])->name('coach.ready');
    Route::get('/box/logo', BoxLogoController::class)->name('box.logo');
    Route::get('/box/settings', [BoxSettingsController::class, 'edit'])->name('box.settings');
    Route::put('/box/settings', [BoxSettingsController::class, 'update'])->name('box.settings.update');
    Route::post('/box/settings/tv', [BoxTvSettingsController::class, 'rotate'])->middleware('throttle:box-tv-rotate')->name('box.tv.rotate');
    Route::get('/box/settings/tv/presence', [BoxTvSettingsController::class, 'presence'])->name('box.tv.presence');
    Route::post('/workouts/{workout}/sessions', [WorkoutSessionController::class, 'store'])->name('sessions.store');
    Route::get('/sessions', [WorkoutSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/{session}/control', [WorkoutSessionController::class, 'control'])->name('sessions.control');
    Route::post('/sessions/{session}/restart', [WorkoutSessionController::class, 'restart'])->name('sessions.restart');
    Route::get('/sessions/{session}/tv', [WorkoutSessionController::class, 'tv'])->name('sessions.tv');
    Route::get('/sessions/{session}/summary', [WorkoutSessionController::class, 'summary'])->name('sessions.summary');
    Route::get('/sessions/{session}/state', [SessionStateController::class, 'show'])->middleware('throttle:session-state')->name('sessions.state');
    Route::post('/sessions/{session}/lease', [SessionStateController::class, 'claim'])->middleware('throttle:session-state')->name('sessions.lease');
    Route::post('/sessions/{session}/commands', [SessionStateController::class, 'command'])->middleware('throttle:session-commands')->name('sessions.commands');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/modalities/{modality:slug}', ModalityController::class)->name('modalities.show');
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule');
    Route::post('/schedule/copy', [ScheduleController::class, 'copy'])->name('schedule.copy');
    Route::get('/load-library', [LoadLibraryController::class, 'index'])->name('loads.index');
    Route::post('/load-library/presets', [LoadLibraryController::class, 'store'])->name('loads.store');
    Route::put('/load-library/presets/{preset}', [LoadLibraryController::class, 'update'])->whereNumber('preset')->name('loads.update');
    Route::post('/load-library/movements', [LoadLibraryController::class, 'storeMovement'])->name('loads.movements.store');
    Route::put('/load-library/movements/{movement}', [LoadLibraryController::class, 'updateMovement'])->whereNumber('movement')->name('loads.movements.update');
    Route::post('/load-library/analyze', [LoadLibraryController::class, 'analyze'])->middleware('throttle:30,1')->name('loads.analyze');
    Route::resource('workouts', WorkoutController::class);
    Route::post('/workouts/{workout}/publish', [WorkoutStatusController::class, 'publish'])->name('workouts.publish');
    Route::post('/workouts/{workout}/draft', [WorkoutStatusController::class, 'draft'])->name('workouts.draft');
    Route::post('/workouts/{workout}/copy', [WorkoutCopyController::class, 'store'])->name('workouts.copy');
    Route::get('/workouts/{workout}/preview', WorkoutPreviewController::class)->name('workouts.preview');
    Route::get('/workouts/{workout}/presentation', WorkoutPreviewController::class)->name('workouts.presentation');
});
