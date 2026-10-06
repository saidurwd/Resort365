<?php

use Illuminate\Support\Facades\Route;
use Modules\Restaurant\Http\Controllers\BillReceiptController;
use Modules\Restaurant\Http\Controllers\DiscountLimitController;
use Modules\Restaurant\Http\Controllers\Kds\KdsController;
use Modules\Restaurant\Http\Controllers\MenuCategoryController;
use Modules\Restaurant\Http\Controllers\MenuImportController;
use Modules\Restaurant\Http\Controllers\MenuItemController;
use Modules\Restaurant\Http\Controllers\ModifierGroupController;
use Modules\Restaurant\Http\Controllers\OutletAccessController;
use Modules\Restaurant\Http\Controllers\OutletController;
use Modules\Restaurant\Http\Controllers\OutletSetupController;
use Modules\Restaurant\Http\Controllers\Pos\PosBillController;
use Modules\Restaurant\Http\Controllers\Pos\PosController;
use Modules\Restaurant\Http\Controllers\Pos\PosOrderController;
use Modules\Restaurant\Http\Controllers\Pos\PosReservationController;
use Modules\Restaurant\Http\Controllers\Pos\PosSessionController;
use Modules\Restaurant\Http\Controllers\PosSessionsController;
use Modules\Restaurant\Http\Controllers\PriceListController;
use Modules\Restaurant\Http\Controllers\PrinterController;
use Modules\Restaurant\Http\Controllers\RestaurantReportController;
use Modules\Restaurant\Http\Controllers\TableReservationController;

/*
|--------------------------------------------------------------------------
| Restaurant web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /restaurant; route names follow `restaurant.resource.action`.
| Setup screens work on the property chosen in the navbar. The POS lives under /pos (below).
|
*/

Route::prefix('restaurant')->name('restaurant.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('can:restaurant.outlet.view')->group(function (): void {
        Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
        Route::get('/outlets/{outlet}', [OutletController::class, 'show'])->whereNumber('outlet')->name('outlets.show');
        Route::get('/printers', [PrinterController::class, 'index'])->name('printers.index');
    });

    Route::middleware('can:restaurant.outlet.manage')->group(function (): void {
        Route::get('/outlets/new', [OutletController::class, 'create'])->name('outlets.create');
        Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store');
        Route::get('/outlets/{outlet}/edit', [OutletController::class, 'edit'])->name('outlets.edit');
        Route::put('/outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');
        Route::post('/printers', [PrinterController::class, 'store'])->name('printers.store');
        Route::put('/printers/{printer}', [PrinterController::class, 'update'])->name('printers.update');
        Route::delete('/printers/{printer}', [PrinterController::class, 'destroy'])->name('printers.destroy');

        Route::prefix('outlets/{outlet}')->name('outlets.')->scopeBindings()->controller(OutletSetupController::class)->group(function (): void {
            Route::post('/stations', 'storeStation')->name('stations.store');
            Route::put('/stations/{station}', 'updateStation')->name('stations.update');
            Route::delete('/stations/{station}', 'destroyStation')->name('stations.destroy');
            Route::post('/terminals', 'storeTerminal')->name('terminals.store');
            Route::put('/terminals/{terminal}', 'updateTerminal')->name('terminals.update');
            Route::post('/terminals/{terminal}/token', 'newToken')->name('terminals.token');
            Route::post('/stations/{station}/display-token', 'displayToken')->name('stations.display-token');
        });
    });

    Route::prefix('outlets/{outlet}')->name('outlets.')->middleware('can:restaurant.floor-plan.manage')->scopeBindings()->controller(OutletSetupController::class)->group(function (): void {
        Route::post('/areas', 'storeArea')->name('areas.store');
        Route::put('/areas/{area}', 'updateArea')->name('areas.update');
        Route::delete('/areas/{area}', 'destroyArea')->name('areas.destroy');
        Route::post('/areas/{area}/positions', 'positions')->name('areas.positions');
        Route::post('/tables', 'storeTable')->name('tables.store');
        Route::put('/tables/{table}', 'updateTable')->name('tables.update');
        Route::delete('/tables/{table}', 'destroyTable')->name('tables.destroy');
    });

    // Menu (Step 3.2): items, categories, modifiers, import; each outlet's price list and schedules.
    Route::prefix('menu')->name('menu.')->group(function (): void {
        Route::middleware('can:restaurant.menu.view')->group(function (): void {
            Route::get('/items', [MenuItemController::class, 'index'])->name('items.index');
            Route::get('/items/data', [MenuItemController::class, 'data'])->name('items.data');
            Route::get('/items/{item}', [MenuItemController::class, 'edit'])->whereNumber('item')->name('items.edit');
            Route::get('/categories', [MenuCategoryController::class, 'index'])->name('categories.index');
            Route::get('/modifiers', [ModifierGroupController::class, 'index'])->name('modifiers.index');
        });
        Route::middleware('can:restaurant.menu.manage')->group(function (): void {
            Route::get('/items/new', [MenuItemController::class, 'create'])->name('items.create');
            Route::post('/items', [MenuItemController::class, 'store'])->name('items.store');
            Route::put('/items/{item}', [MenuItemController::class, 'update'])->whereNumber('item')->name('items.update');
            Route::post('/categories', [MenuCategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [MenuCategoryController::class, 'update'])->name('categories.update');
            Route::post('/modifiers', [ModifierGroupController::class, 'store'])->name('modifiers.store');
            Route::put('/modifiers/{group}', [ModifierGroupController::class, 'update'])->name('modifiers.update');
            Route::get('/import', [MenuImportController::class, 'create'])->name('import');
            Route::post('/import', [MenuImportController::class, 'store'])->name('import.store');
            Route::get('/import/template', [MenuImportController::class, 'template'])->name('import.template');
        });
    });

    Route::prefix('outlets/{outlet}')->whereNumber('outlet')->name('outlets.')->scopeBindings()->group(function (): void {
        // The price list is open to setup viewers and to staff who mark items sold out (OutletPolicy::viewPrices).
        Route::get('/prices', [PriceListController::class, 'show'])->name('prices');
        Route::post('/prices/{price}/sold-out', [PriceListController::class, 'soldOut'])->name('prices.sold-out');
        Route::middleware('can:restaurant.price.manage')->group(function (): void {
            Route::put('/prices', [PriceListController::class, 'update'])->name('prices.update');
            Route::post('/prices/copy', [PriceListController::class, 'copy'])->name('prices.copy');
            Route::post('/schedules', [PriceListController::class, 'storeSchedule'])->name('schedules.store');
            Route::put('/schedules/{schedule}', [PriceListController::class, 'updateSchedule'])->name('schedules.update');
        });
    });

    Route::middleware('can:restaurant.session.view')->group(function (): void {
        Route::get('/sessions', [PosSessionsController::class, 'index'])->name('sessions.index');
        Route::get('/sessions/data', [PosSessionsController::class, 'data'])->name('sessions.data');
        Route::get('/sessions/{session}/report', [PosSessionsController::class, 'report'])->name('sessions.report');
    });

    Route::get('/bills/{bill}/receipt', [BillReceiptController::class, 'show'])->whereNumber('bill')->name('bills.receipt');

    foreach (RestaurantReportController::REPORTS as $report) {
        Route::get('/reports/'.$report, [RestaurantReportController::class, 'show'])->defaults('report', $report)->middleware('can:restaurant.report.view')->name('reports.'.$report);
    }

    Route::get('/reservations', [TableReservationController::class, 'index'])->middleware('can:restaurant.reservation.view')->name('reservations.index');
    Route::middleware('can:restaurant.reservation.manage')->prefix('reservations')->name('reservations.')->group(function (): void {
        Route::post('/', [TableReservationController::class, 'store'])->name('store');
        Route::put('/{reservation}', [TableReservationController::class, 'update'])->whereNumber('reservation')->name('update');
        Route::post('/{reservation}/close', [TableReservationController::class, 'close'])->whereNumber('reservation')->name('close');
    });

    Route::middleware('can:restaurant.discount-limit.manage')->group(function (): void {
        Route::get('/discount-limits', [DiscountLimitController::class, 'index'])->name('discount-limits.index');
        Route::put('/discount-limits', [DiscountLimitController::class, 'update'])->name('discount-limits.update');
    });

    Route::get('/access', [OutletAccessController::class, 'index'])->middleware('can:restaurant.access.manage')->name('access.index');
    Route::put('/access', [OutletAccessController::class, 'update'])->middleware('can:restaurant.access.manage')->name('access.update');
});

/*
| The POS (ARCHITECTURE §10.1, full-screen layout): only on registered terminals (pos.terminal), and
| for actions only with a person signed in by PIN who may work there (pos.staff). Not behind the
| normal auth middleware: the lock screen is the POS's own sign-in.
*/
Route::prefix('pos')->name('pos.')->group(function (): void {
    Route::get('/register', [PosController::class, 'register'])->name('register');
    Route::post('/register', [PosController::class, 'storeDevice'])->middleware('throttle:10,1')->name('register.store');

    Route::middleware('pos.terminal')->group(function (): void {
        Route::get('/', [PosController::class, 'home'])->name('home');
        Route::post('/sign-in', [PosController::class, 'signIn'])->middleware('throttle:30,1')->name('sign-in');
        Route::post('/lock', [PosController::class, 'lock'])->name('lock');

        Route::middleware('pos.staff')->group(function (): void {
            Route::get('/main', [PosSessionController::class, 'main'])->name('main');
            Route::post('/session', [PosSessionController::class, 'open'])->name('session.open');
            Route::post('/session/close', [PosSessionController::class, 'close'])->name('session.close');
            Route::get('/sessions/{session}/report', [PosSessionController::class, 'report'])->name('sessions.report');
            Route::post('/api/approvals', [PosSessionController::class, 'approve'])->middleware('throttle:30,1')->name('approvals.store');

            // Orders and kitchen tickets (Step 3.4): the floor, the order screen and its JSON endpoints.
            Route::middleware('can:restaurant.order.take')->controller(PosOrderController::class)->group(function (): void {
                Route::get('/floor', 'floor')->name('floor');
                Route::post('/orders', 'open')->name('orders.open');
                Route::get('/orders/{order}', 'show')->name('orders.show');
                Route::get('/kots/{kot}/print', 'printKot')->name('kots.print');

                // Live updates (Step 3.5): what the screens reload when told of a change, or poll without WebSockets.
                Route::get('/api/floor', 'floorData')->name('floor.data');
                Route::get('/api/menu', 'menu')->name('menu');
                Route::post('/api/orders/{order}/delivery', 'delivery')->name('orders.delivery');
                Route::get('/api/ready', 'ready')->name('ready');

                Route::prefix('api/orders/{order}')->name('orders.')->scopeBindings()->group(function (): void {
                    Route::get('/', 'data')->name('data');
                    Route::post('/lines', 'addLine')->name('lines.store');
                    Route::patch('/lines/{line}', 'changeLine')->name('lines.update');
                    Route::delete('/lines/{line}', 'removeLine')->name('lines.destroy');
                    Route::post('/lines/{line}/void', 'voidLine')->name('lines.void');
                    Route::post('/send', 'send')->name('send');
                    Route::post('/transfer', 'transfer')->name('transfer');
                    Route::post('/merge', 'merge')->name('merge');
                    Route::post('/cancel', 'cancel')->name('cancel');
                });
            });

            // Table reservations on the floor (Step 3.8).
            Route::middleware('can:restaurant.order.take')->controller(PosReservationController::class)->group(function (): void {
                Route::post('/reservations/{reservation}/seat', 'seat')->name('reservations.seat');
                Route::post('/reservations/{reservation}/close', 'close')->name('reservations.close');
            });

            // Bills, discounts and payments (Step 3.6).
            Route::middleware('can:restaurant.order.take')->controller(PosBillController::class)->group(function (): void {
                Route::get('/orders/{order}/bill', 'show')->name('orders.bill');
                Route::get('/bills/{bill}/print', 'printView')->name('bills.print');
                Route::get('/bills/{bill}/receipt', 'receipt')->name('bills.receipt');
                Route::post('/api/orders/{order}/discount', 'discount')->name('orders.discount');
                Route::post('/api/orders/{order}/bill/preview', 'preview')->name('orders.bill.preview');
                Route::post('/api/orders/{order}/bill/print', 'print')->name('orders.bill.print');
                Route::post('/api/orders/{order}/reopen', 'reopen')->name('orders.reopen');
                Route::get('/api/stays', 'stays')->name('stays');
                Route::get('/api/orders/{order}/meal-plan', 'mealPlanLeft')->name('orders.meal-plan');
                Route::post('/api/orders/{order}/meal-plan', 'redeem')->name('orders.meal-plan.redeem');
                Route::delete('/api/orders/{order}/meal-plan', 'clearMealPlan')->name('orders.meal-plan.clear');
            });
            Route::middleware('can:restaurant.bill.settle')->controller(PosBillController::class)->group(function (): void {
                Route::post('/api/bills/{bill}/payments', 'pay')->name('bills.pay');
                Route::get('/api/companies', 'companies')->name('companies');
                Route::post('/api/bills/{bill}/comp', 'comp')->name('bills.comp');
                Route::post('/api/bills/{bill}/void', 'void')->name('bills.void');
            });
        });
    });
});

/*
| The kitchen display (Step 3.5, ARCHITECTURE §10.1): a station's screen registered with its display
| token (no idle timeout), or a person signed in to the app with restaurant.kds.use who picks a station.
*/
Route::prefix('kds')->name('kds.')->controller(KdsController::class)->group(function (): void {
    Route::get('/register', 'register')->name('register');
    Route::post('/register', 'storeDevice')->middleware('throttle:10,1')->name('register.store');
    Route::get('/stations', 'stations')->middleware(['auth', 'verified', 'can:restaurant.kds.use'])->name('stations');

    Route::middleware('kds.station')->group(function (): void {
        Route::get('/', 'board')->name('board');
        Route::post('/sign-out', 'signOut')->name('sign-out');
        Route::get('/api/board', 'boardData')->name('api.board');
        Route::post('/api/kots/{kot}', 'progress')->middleware('throttle:120,1')->name('api.progress');
    });
});
