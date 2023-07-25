<?php

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriodImmutable;

/**
 * To change a date into Y-m-d format
 * @param $param
 * @param string $format
 * @return string
 */
function getDateFormat($param, string $format = DATE_FORMAT_WITH_DATE_DEFAULT)
{
    return $param ? Carbon::parse($param)->format($format) : '-';
}

/**
 * Store Files to Storage
 *
 * @param string|object $field
 * <p>Field name from the request() for which the File has to be stored.</p>
 * @param string $path
 * <p>The path in which the file has to be stored in the S3.</p>
 * <p>If No path is specified, then it will default to 'public'.</p>
 * @return string
 * <p>Returns the FileName with $path specified to store in the DB.</p>
 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
 * <p>Throws \Illuminate\Contracts\Filesystem\FileNotFoundException if the request doesn't have a File
 * mentioned in the $field parameter<p>
 */
function storeFile($field, string $path = 'public'): string
{
    if ((is_string($field) && !request()->hasFile($field)) || (!is_string($field) && !is_file($field))) {
        throw new \Illuminate\Contracts\Filesystem\FileNotFoundException();
    }

    $file = is_string($field) ? request()->file($field) : $field;

    $baseFilename = $file->getBasename();
    $extension = $file->getClientOriginalExtension();
    $filename = $file->getClientOriginalName();

    // Gives extra buffer length : 5 => '/', '_', '.', Buffer(2)
    $maxFileNameLength = 255 - strlen($path) - strlen($baseFilename) - strlen($extension) - 5;

    // Get only FileName
    $filename = pathinfo($filename, PATHINFO_FILENAME);

    // Trim Filename if file name exceeds the limit of 255.
    $filename = substr($filename, 0, ($maxFileNameLength));

    // Remove '/', '\' in Filename
    $filename = str_replace('/', '_', $filename);
    $filename = str_replace('\\', '_', $filename);

    $path = \Illuminate\Support\Str::endsWith($path, '/') ? $path : ($path . '/');

    // Final File name with Path to store
    $filename = $path . $filename . '_' . $baseFilename . '.' . $extension;

    // Upload File
    \Illuminate\Support\Facades\Storage::put($filename, fopen($file, 'r+'));

    return $filename;
}

/**
 * Retrieve File From Storage
 *
 * @param $filePath
 * @return string
 */
function retrieveFile($filePath)
{
    if (empty($filePath)) {
        return null;
    }

    $expiry = now()->addMinutes(config('filesystems.disks.s3.ttl'));

    $method = 'url';
    if (config('filesystems.default') == 's3') {
        $method = 'temporaryUrl';
    }

    return \Illuminate\Support\Facades\Storage::$method($filePath, $expiry);
}

/**
 * Retrieve File From Storage
 *
 * @param $filePath
 * @return bool
 */
function fileExists($filePath)
{
    if (empty($filePath)) {
        return false;
    }

    return \Illuminate\Support\Facades\Storage::exists($filePath);
}

/**
 * To get the profile image for user
 *
 * @param $imageName
 * @return string|null
 */
function getProfileImage($imageName)
{
    $file = retrieveFile($imageName);

    if (!empty($imageName) && isset($file)) {
        return $file;
    }

    return asset('assets/svgs/user-profile.svg');
}

/**
 * Get Authenticated Profile Picture URL
 *
 * @return string|null
 */
function getAuthUserProfileImage()
{
    if (auth()->check()) {
        return auth()->user()->getProfilePicture();
    }

    return getProfileImage(null);
}

/**
 * Get SVG content
 *
 * @param $file <p>Avoid using dot(.) in the file name. as dot will be considered for subdirectories.</p>
 * @return string
 */
function getSVG($file)
{
    $file = str_replace('.', '/', $file);

    $filePath = public_path('assets/svgs/' . $file . '.svg');

    if (file_exists($filePath)) {
        return file_get_contents($filePath);
    }

    return '';
}

/**
 * Split the key and value of the given array into a separate array
 *
 * @param array $array The Array to be Split
 * @param string $keySlug
 * @param string $valueSlug
 * @return array
 */
function array_split(array $array, string $keySlug = 'key', string $valueSlug = 'value')
{
    $id = 1;
    $finalArray = [];

    foreach ($array as $key => $value) {
        $finalArray[] = [
            'id' => $id++,
            $keySlug => $key,
            $valueSlug => $value
        ];
    }

    return $finalArray;
}

/**
 * @param $dateTime
 * @return Carbon
 */
function parseDateTimeFromString($dateTime)
{
    if (isset($dateTime)) {
        try {
            $dateTime = str_replace('/', '-', $dateTime);

            return Carbon::parse($dateTime);
        } catch (\Throwable $throwable) {
            logError($throwable, 'Error while parsing Date and Time From String', 'app/Helpers/helpers.php -> parseDateTimeFromString()', [
                'time' => $dateTime,
                'requests' => request()->all(),
            ]);
        }
    }

    return null;
}

/**
 * @param $dateTime
 * @param string $format
 * @param string $default
 * @return string
 */
function parseDateTime($dateTime, $format = DATE_FORMAT_HUMAN, $default = '-')
{
    return isset($dateTime)
        ? parseDateTimeFromString($dateTime)->format($format)
        : $default;
}

/**
 * Get the current route name.
 *
 * @return string|null
 * @return array|null
 */
function currentRouteName()
{
    return \Illuminate\Support\Facades\Route::currentRouteName();
}

/**
 * Common error log method for logging errors with a custom format
 *
 * @param $errorObject
 * @param $message
 * @param $location
 * @param array $reference
 */
function logError($errorObject, $message, $location, $reference = [])
{
    \Log::error([
        $message => [
            'location' => $location,
            'route' => currentRouteName(),
            'message' => $errorObject->getMessage(),
            'reference' => $reference,
            'trace' => $errorObject->getTraceAsString(),
        ]
    ]);
}

/**
 * Trim Spaces in the given string
 *
 * @param $string
 * @return array|string|string[]
 */
function trimSpaces($string)
{
    return str_replace(' ', '', $string);
}

/**
 * @param $status
 * @param $color
 * @param string $default
 * @return string
 */
function getTag($status, $color, $default = '-')
{
    if (isset($status)) {
        return '<div><span class="text-' . $color . ' bg-' . $color . '-10 rounded-xl px-1.5 py-0.5">' . $status . '</span></div>';
    }

    return $default;
}

/**
 * @param $status
 * @return string
 */
function getProjectStatusTag($status)
{
    $colors = [
        PROJECT_STATUS_PROPOSAL => 'dark-red',
        PROJECT_STATUS_IN_PROGRESS => 'mild-green',
        PROJECT_STATUS_COMPLETED => 'dark-black',
    ];

    return getTag(PROJECT_STATUSES[$status] ?? null, $colors[$status] ?? null);
}

/**
 * @param $status
 * @return string
 */
function getUserStatusTag($status)
{
    $colors = [
        USER_STATUS_UN_VERIFIED => 'dark-red',
        USER_STATUS_INTERN => 'dark-blue',
        USER_STATUS_WORKING => 'mild-green',
        USER_STATUS_RELIEVED => 'dark-black',
    ];

    return getTag(USER_STATUSES_ALL[$status] ?? null, $colors[$status] ?? null);
}

/**
 * To convert a double to hours-minutes for timesheet total hours
 *
 * @param $value
 * @return string
 */
function convertValueToHoursMinutes($value)
{
    $splitValue = explode('.', $value);
    $result = '<span><span class="text-dark-black font-bold text-base line-height-unset">' . $splitValue[0] . '</span><span class="ml-2px">hrs</span>';
    if (count($splitValue) === 2) {
        if ($splitValue[1] === '25') {
            $mins = '15';
        } elseif ($splitValue[1] === '5') {
            $mins = '30';
        } elseif ($splitValue[1] === '75') {
            $mins = '45';
        } else {
            $mins = '00';
        }
        $result .= '<span class="ml-4px text-dark-black font-bold text-base line-height-unset"
                              >' . $mins . '</span><span class="ml-2px">mins</span>';
    }
    $result .= '</span>';
    return ($result);
}

/**
 * Check if Authenticated User has the specified Permission
 *
 * @param $permission
 * @return false
 */
function authPermission($permission)
{
    if (auth()->check()) {
        return auth()->user()->isAbleTo($permission);
    }
    return false;
}

/**
 * @return string[][]
 */
function getTimeSheetTiming()
{
    return [
        ['key' => '0.25', 'value' => '0:15', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">15</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '0.50', 'value' => '0:30', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">30</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '0.75', 'value' => '0:45', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">45</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '1.00', 'value' => '1:00', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">1</span><span class="ml-2px text-xs">hr</span></span>'],
        ['key' => '1.25', 'value' => '1:15', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">1</span><span class="ml-2px text-xs">hr</span><span class="ml-2 text-dark-black text-base line-height-unset font-medium">15</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '1.50', 'value' => '1:30', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">1</span><span class="ml-2px text-xs">hr</span><span class="ml-2 text-dark-black text-base line-height-unset font-medium">30</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '1.75', 'value' => '1:45', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">1</span><span class="ml-2px text-xs">hr</span><span class="ml-2 text-dark-black text-base line-height-unset font-medium">45</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '2.00', 'value' => '2:00', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">2</span><span class="ml-2px text-xs">hrs</span></span>'],
        ['key' => '2.25', 'value' => '2:15', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">2</span><span class="ml-2px text-xs">hrs</span><span class="ml-2 text-dark-black text-base line-height-unset font-medium">15</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '2.50', 'value' => '2:30', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">2</span><span class="ml-2px text-xs">hrs</span><span class="ml-2 text-dark-black text-base line-height-unset font-medium">30</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '2.75', 'value' => '2:45', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">2</span><span class="ml-2px text-xs">hrs</span><span class="ml-2 text-dark-black text-base line-height-unset font-medium">45</span><span class="ml-2px text-xs">mins</span></span>'],
        ['key' => '3.00', 'value' => '3:00', 'display_value' => '<span><span class="text-dark-black text-base line-height-unset font-medium">3</span><span class="ml-2px text-xs">hrs</span></span>'],
    ];
}

/**
 * @return string[][]
 */
function getTimeSheetApproveTiming()
{
    $timing = [
        '3.00' => '3:00', '2.75' => '2:45', '2.50' => '2:30', '2.25' => '2:15',
        '2.00' => '2:00', '1.75' => '1:45', '1.50' => '1:30', '1.25' => '1:15',
        '1.00' => '1:00', '0.75' => '0:45', '0.50' => '0:30', '0.25' => '0:15',
    ];

    return array_split($timing);
}

/**
 * @param $hour
 * @param string $default
 * @return string
 */
function getTimesheetHourDisplay($hour, $default = '-')
{
    $time = collect(getTimeSheetApproveTiming())
        ->where('key', $hour)
        ->first();

    return $time['value'] ?? $default;
}

/**
 * @param $duration
 * @return string
 */
function getTimesheetHeaderHourDisplay($duration)
{
    $minutesDisplay = [
        '0' => '00',
        '00' => '00',
        '25' => '15',
        '5' => '30',
        '50' => '30',
        '75' => '45',
    ];

    $duration = explode('.', $duration);

    $hours = $duration[0];
    $minutes = $duration[1] ?? '00';

    return $hours . ':' . ($minutesDisplay[$minutes] ?? '00');
}

/**
 * Get Timesheet group By options.
 *
 * @return string[][]
 */
function getTimesheetGroupByOptions()
{
    $groupByOptions = [
        ['key' => 'date', 'value' => 'Date'],
        ['key' => 'user', 'value' => 'User'],
    ];

    if (authPermission(['timesheet.approve', 'timesheet.authorize', 'timesheet.billable'])) {
        $groupByOptions[] = ['key' => 'project', 'value' => 'Project'];
    }

    return $groupByOptions;
}

/**
 * @param $sentence
 * @param $count
 * @return string
 */
function getAcronym($sentence, $count = null)
{
    $acronym = '';

    foreach (preg_split("/\s+/", $sentence) as $word) {
        $acronym .= ($word[0] ?? '');
    }

    $acronym = str_replace('-', '', $acronym);
    $acronym = str_replace('_', '', $acronym);
    $acronym = str_replace('.', '', $acronym);

    return strtoupper(substr($acronym, 0, $count ?? strlen($acronym)));
}

/**
 * Get percentage of a value
 *
 * @param $value
 * @param int $total
 * @param int $precision
 * @return string
 */
function getPercentage($value, $total = 100, $precision = 2)
{
    if (empty($total)) {
        return '0.00';
    }

    $percentage = ($value / $total) * 100;

    return number_format($percentage, $precision);
}

/**
 * Get options in place of Timesheet more dropdown.
 *
 * @return string[][]
 */
function getTimesheetMoreOptions()
{
    $data = [
        ['key' => 'options', 'value' => 'Options', 'on_click' => 'prepareTimesheetOptionsModal()'],
        ['key' => 'tracker', 'value' => 'Tracker', 'link' => route('timesheet.tracker.index')],
    ];

    if (authPermission('timesheet.export')) {
        $data[] = ['key' => 'export_excel', 'value' => 'Export Excel'];
    }

    return $data;
}

/**
 * @param $achievedPercentage
 * @return string
 */
function getCriticalLevel($achievedPercentage)
{
    $output = "-";
    if ($achievedPercentage >= 0) {
        $output = "<span class='text-mild-green bg-mild-green-10 rounded-xl px-1.5 py-0.5'>NA</span>";
    } elseif ($achievedPercentage >= -5) {
        $output = "<span class='text-yellow bg-dark-yellow rounded-xl px-1.5 py-0.5'>Low</span>";
    } elseif ($achievedPercentage < -5 && $achievedPercentage >= -10) {
        $output = "<span class='text-orange-600 bg-primary rounded-xl px-1.5 py-0.5'>Moderate</span>";
    } elseif ($achievedPercentage < -10 && $achievedPercentage >= -20) {
        $output = "<span class='text-red-600 bg-red-50 rounded-xl px-1.5 py-0.5'>High</span>";
    } elseif ($achievedPercentage < -20) {
        $output = "<span class='text-dark-red bg-red-50 rounded-xl px-1.5 py-0.5'>Severe</span>";
    }
    return $output;
}


/**
 * @param $field
 * @param $data
 * @return mixed|null
 */
function getOldData($field, $data = null)
{
    try {
        if (session()->has('errors') && session()->get('errors')->has($field)) {
            return null;
        }

        return old($field, $data);
    } catch (\Throwable $exception) {
        logError($exception, 'Error while getting Old Data', __FUNCTION__);
        return null;
    }
}

/**
 * Get the current month.
 *
 * @return string|null
 */
function remainingFinalcialYearMonth()
{
    $currentMonth = now()->month;
    $currentYear = now()->year;
    $endYear = ($currentMonth <= 3) ? $currentYear : $currentYear + 1;
    $endYear = Illuminate\Support\Carbon::createFromFormat('d-m-Y', '31-3-' . $endYear);

    return $endYear->diffInMonths(now());
}

/**
 * Get the current month.
 *
 * @return bool
 */
function canGeneratePayslip()
{
    $date = parseDateTimeFromString('26-' . now()->format('m-Y') . '12:00:00');
    return (now()->greaterThanOrEqualTo($date));
}

/**
 * check for is there any pending LOP for this month.
 *
 * @return bool
 */
function isPendingLOP()
{
    return \App\Models\Lop::query()->where('status', '=', LOP_STATUS_PENDING)->exists();
}

/**
 * check is user joined this month.
 *
 * @param $doj
 * @return bool
 */
function isUserJoinedThisMonth($doj)
{
    $currentMonthYear = now()->format('m-Y');
    $doj = $doj->format('m-Y');
    return $currentMonthYear === $doj;
}

/**
 * return the number of days worked in a joining month.
 *
 * @param $doj
 * @return int
 */
function daysWorkedAfterJoiningInaMonth($doj)
{
    $date = $doj->format('d');
    $monthNumber = now()->format('m');
    return now()->month($monthNumber)->daysInMonth;
    -$date;;
}

function showFinancialYear()
{
    $currentMonth = now()->month;
    $currentYear = now()->year;
    $startYear = ($currentMonth <= 3) ? ($currentYear - 1) : $currentYear;
    $endYear = ($currentMonth <= 3) ? $currentYear : ($currentYear + 1);
    return "1 April " . $startYear . " - 31 march " . $endYear;
}

function mathRound($number, $to = 12)
{
    return round($number / $to, 0) * $to;
}

/**
 * @param $monthYear
 * @param $connectingSlug
 * @return string
 */
function currentFinancialYear($monthYear = null, $connectingSlug = '-')
{
    if (is_string($monthYear)) {
        $monthYear = parseDateTimeFromString('01-' . $monthYear);
    }

    $currentMonth = $monthYear ? $monthYear->format('m') : now()->month;
    $currentYear = $monthYear ? $monthYear->format('Y') : now()->year;
    $startYear = ($currentMonth <= 3) ? ($currentYear - 1) : $currentYear;
    $endYear = ($currentMonth <= 3) ? $currentYear : ($currentYear + 1);
    return $startYear . $connectingSlug . $endYear;
}

/**
 * @param $monthYear
 * @return string
 */
function currentFinancialYearMailFormat($monthYear = null)
{
    return currentFinancialYear($monthYear, '_to_');
}

/**
 * Get Financial Year for the given year and month
 *
 * @param null $year
 * @param null $month
 * @param null $format
 * @return array
 */
function getFinancialYear($year = null, $month = null, $format = null)
{
    $year = $year ?? now()->get('year');
    $month = $month ?? now()->get('month');
    $month = (!(int)$month) ? (new Carbon($month))->month : $month;

    $startDate = Carbon::create($month < 4 ? $year - 1 : $year, CarbonInterface::APRIL)->startOfMonth();
    $endDate = Carbon::create($month < 4 ? $year : $year + 1, CarbonInterface::MARCH)->endOfMonth();

    return [
        $format ? $startDate->format($format) : $startDate,
        $format ? $endDate->format($format) : $endDate,
    ];
}

/**
 * @param $monthYear
 * @return string
 */
function getPayslipTemplateBladeViewName($monthYear)
{
    $year = explode('-', $monthYear)[1] ?? 2023;    // Default to 2023
    $month = explode('-', $monthYear)[0] ?? CarbonInterface::APRIL;  // Default to April (Financial year starting)

    $financialYearFormat = implode('_', getFinancialYear($year, $month, 'Y'));

    if (file_exists(resource_path('views/common/payslip/' . $financialYearFormat . '.blade.php'))) {
        return 'common/payslip/' . $financialYearFormat;
    }

    // By Default, it will take 2023 - 2024 Financial Year
    return 'common/payslip/' . implode('_', getFinancialYear(2023, CarbonInterface::APRIL, 'Y'));
}

function getNumberOfWorkingDays($isSaturdayWorking)
{
    $dt = now()->startOfMonth();
    $dt2 = now()->endOfMonth();

    $weekEndDays = $isSaturdayWorking ? [CarbonInterface::SUNDAY] : [CarbonInterface::SATURDAY, CarbonInterface::SUNDAY];
    Carbon::setWeekendDays($weekEndDays);

    return $dt->diffInDaysFiltered(function ($date) {
        return !$date->isWeekend();
    }, $dt2);
}

function calculateForLop($amount, $numberOfDaysInMonth, $lop, $numberOfLeaveDays, $numberOfWorkingDays)
{
    return round(($amount - ($amount / $numberOfDaysInMonth) * (($lop) + ($lop * ($numberOfLeaveDays / $numberOfWorkingDays)))));
}

function isLOPGeneratedForCurrentMonth()
{
    $date = parseDateTimeFromString(now()->format('Y-m') . '-26');
    return \Illuminate\Support\Facades\DB::table('scheduler_watch')
        ->where('date', $date)
        ->where('command', '=', 'lop:generate-report')
        ->where('status', '=', SCHEDULER_STATUS_SUCCESS)
        ->exists();
}

/**
 * @param $amount
 * @param bool $decimal
 * @return string
 */
function formatCurrency($amount, $decimal = true)
{
    if ($decimal) {

        $amount = number_format($amount, 2, '.', '');
    }

    $temp = preg_replace('/\B(?=(\d{2})+(\d{3})(?!\d))/i', ',', $amount);

    return preg_replace('/\B(?=(\d{3})+(?!\d))/i', ',', $temp);
}

/**
 * To check the user have access to CTC module
 *
 * @return boolean
 */
function ableToAccessCtcModule()
{
    return in_array(auth()->user()->employee_id, config('env.ctc_permission_users'));
}

/**
 * @return mixed
 */
function todayDeclaredHoliday(): mixed
{
    return \App\Models\Holiday::getCurrentYearHolidays()->where('date_of_holiday', now()->toDateString());
}

/**
 * @param $authUser
 * @return \Carbon\CarbonImmutable
 */
function getUserPreviousWorkingDay($authUser): \Carbon\CarbonImmutable
{
    $previousWorkingDay = \Carbon\CarbonImmutable::now()->subDay();
    $holidays = \App\Models\Holiday::getHolidaysBetween($previousWorkingDay->subMonthNoOverflow()->toDateString(), $previousWorkingDay->toDateString());

    \Carbon\CarbonImmutable::setWeekendDays($authUser->getWeekends());

    while (in_array($previousWorkingDay->toDateString(), $holidays) || $previousWorkingDay->isWeekend()) {
        $previousWorkingDay = $previousWorkingDay->subDay();
    }

    return $previousWorkingDay;
}

/**
 * @return bool
 */
function isOfficeWorkingDay(): bool
{
    if (today() < parseDateTimeFromString(CHECK_IN_START_DATE)) {
        return false;
    }
    return !(todayDeclaredHoliday()->count() > 0 || today()->isSunday());
}

/**
 * @param $success
 * @param $icon
 * @param $title
 * @return array
 */
function sendResponse($success, $icon, $title): array
{
    return [
        'success' => $success,
        'icon' => $icon,
        'title' => $title,
    ];
}

/**
 * @return \Illuminate\Http\JsonResponse
 */
function errorResponse(): \Illuminate\Http\JsonResponse
{
    return response()->json([
        'success' => false,
        'icon' => 'error',
        'title' => 'Error',
        'message' => 'Something went wrong. Please try again later.',
    ], 500);
}

/**
 * @return CarbonInterface
 */
function getCurrentMonthFirstWorkingDay(): CarbonInterface
{
    $currentDate = CarbonImmutable::now();
    $period = CarbonPeriodImmutable::between($currentDate->startOfMonth(), $currentDate->endOfMonth())->filter('isWeekday');
    $holidays = Holiday::whereBetween('date_of_holiday', [now()->startOfMonth(), now()->endOfMonth()])->select('date_of_holiday')->get();
    $firstWorkingDay = $period->first();

    foreach ($period as $date) {
        if (!$holidays->contains('date_of_holiday', $date->format('Y-m-d'))) {
            $firstWorkingDay = $date;
            break;
        }
    }

    return $firstWorkingDay->startOfDay();
}

/**
 * @param $fractionalHours
 * @return string
 */
function getActualHoursMinutes($fractionalHours): string
{
    $fractionalHours = max($fractionalHours, 0);
    $hours = floor($fractionalHours);
    $minutes = round(($fractionalHours - $hours) * 60);

    return $hours. ' hrs ' . $minutes . ' mins';
}

/**
 * @return float|int
 */
function getUserCheckOutTime(): float|int
{
    if (auth()->user()->branch_id == UK_BRANCH_ID) {
        return 7.5;
    }

    return 8;
}

function getNumberOfWeekEndDays($dt, $dt2, $isSaturdayWorking)
{
    $weekEndDays = $isSaturdayWorking ? [\Carbon\Carbon::SUNDAY] : [\Carbon\Carbon::SATURDAY, \Carbon\Carbon::SUNDAY];
    \Carbon\Carbon::setWeekendDays($weekEndDays);

    return $dt->diffInDaysFiltered(function ($date) {
        return $date->isWeekend();
    }, $dt2);
}

function getWeekendDates($startDate, $endDate, $isSaturdayWorking)
{
    $dates = [];

    $weekEnd = $isSaturdayWorking ? 7 : 6;
    while ($startDate <= $endDate) {
        if ($startDate->format('N') >= $weekEnd) { // Check if the day is Saturday (6) or Sunday (7)
            $dates[] = $startDate->format('d');
        }
        $startDate->modify('+1 day');
    }
    return $dates;
}

function getNumberOfSaturdays($startDate, $endDate) {
    \Carbon\Carbon::setWeekendDays([\Carbon\Carbon::SATURDAY]);

    return $startDate->diffInDaysFiltered(function ($date) {
        return $date->isWeekend();
    }, $endDate);
}

function getDayNamesAndDates($startDate, $endDate)
{
    $days = [];
    while ($startDate->lte($endDate)) {
        $dayNumber = $startDate->day;
        $dayName = $startDate->format('l');

        $days[$dayNumber] = $dayName;

        $startDate->addDay();
    }

    return $days;
}
