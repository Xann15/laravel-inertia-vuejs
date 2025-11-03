<?php

namespace App\Services;

use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\CodeGeneratorService;
use App\Enums\TransType;
use App\Http\Controllers\StoreController;
use App\Enums\RoomStat;
use Illuminate\Support\Facades\Date;
use Illuminate\Http\Request;

class PublicServices
{
    // /**
    //  * Get active tariff rate
    //  *
    //  * @param int $tariffID
    //  * @param Carbon|string $arrivalDate
    //  * @param int &$rateID
    //  * @param float &$xBed
    //  * @param Carbon|string|null $departDate
    //  * @return float
    //  */
    public static function getRate(int $tariffID, $arrivalDate, int &$rateID, float &$xBed = 0.0, $departDate = null): float
    {
        // normalize dates to strings yyyy-mm-dd
        $arrival = $arrivalDate instanceof Carbon ? $arrivalDate->toDateString() : Carbon::parse($arrivalDate)->toDateString();
        $hasDepart = $departDate !== null && $departDate !== '';
        $depart = $hasDepart ? ($departDate instanceof Carbon ? $departDate->toDateString() : Carbon::parse($departDate)->toDateString()) : null;

        $sql = "
            SELECT
              tbltariff.rateid AS rateid,
              tbltariff.Tariff AS Tariff,
              tbltariff.xtraBed AS xtraBed
            FROM tbltariff
            JOIN tblseason ON tbltariff.seasonID = tblseason.seasonID
            JOIN tblrate   ON tbltariff.rateid   = tblrate.rateid
            JOIN tblroomtype ON tbltariff.roomTypeID = tblroomtype.roomTypeID
            WHERE tbltariff.tariffID = :tariffID
              AND ((tbltariff.isActive <> 0) OR (tbltariff.isChild <> 0))
        ";

        $bindings['tariffID'] = $tariffID;

        if ($hasDepart) {
            $sql .= "
              AND (DATE(fromDate) <= DATE(:depart))
              AND (DATE(toDate)   >= DATE(:arrival))
              AND (DATE(startDate) <= DATE(:depart1))
              AND (DATE(ExpiryDate) >= DATE(:arrival1))
            ";
            $bindings['depart'] = $depart;
            $bindings['arrival'] = $arrival;
            $bindings['depart1'] = $depart;
            $bindings['arrival1'] = $arrival;
        } else {
            $sql .= "
              AND (DATE(fromDate) <= DATE(:arrival))
              AND (DATE(toDate)   >= DATE(:arrival1))
              AND (DATE(startDate) <= DATE(:arrival2))
              AND (DATE(ExpiryDate) >= DATE(:arrival3))
            ";
            $bindings['arrival'] = $arrival;
            $bindings['arrival1'] = $arrival;
            $bindings['arrival2'] = $arrival;
            $bindings['arrival3'] = $arrival;
        }

        $sql .= " LIMIT 1";

        $row = DB::selectOne($sql, $bindings);

        if ($row !== null && isset($row->rateid)) {
            $xBed = isset($row->xtraBed) ? (float)$row->xtraBed : 0.0;
            $rateID = (int)$row->rateid;
            return isset($row->Tariff) ? (float)$row->Tariff : 0.0;
        }

        $rateID = 0;
        return 0.0;
    }

    /**
     * Check if a segment is active
     *
     * @param int $segmentID
     * @return bool
     */
    public static function isSegmentActive(int $segmentID): bool
    {
        $value = DB::table('tblsegment')
            ->where('segmentID', $segmentID)
            ->value('isSActive');

        return (bool) $value;
    }

    /**
     * Get house use rows for a date, and sum adults by reference
     *
     * @param Carbon|string $dtToCheck
     * @param int &$Adult
     * @param bool $isThread  (kept for API parity, not used)
     * @return Collection
     */
    public static function getHouseUse($dtToCheck, int &$Adult = 0)
    {
        $date = $dtToCheck instanceof Carbon ? $dtToCheck->toDateString() : Carbon::parse($dtToCheck)->toDateString();

        $rows = DB::table('tbltrans')
            ->selectRaw("concat_ws(' ', tbltrans.tGuestName, tbltrans.tGuestLastName) AS GuestName")
            ->addSelect('tbltrans.ArrivalDate', 'tbltrans.DepartDate', 'tblfoliomaster.mFolioNo AS FolioNo')
            ->addSelect('tbltrans.roomNo', 'tblroomtype.roomType', 'tbltrans.Adult', 'tbltrans.noMice', 'tbltrans.NoTrans')
            ->where('tbltrans.roomNo', '<>', 0)
            ->whereRaw('DATE(tbltrans.ArrivalDate) <= DATE(?)', [$date])
            ->whereRaw('DATE(tbltrans.DepartDate) >= DATE(?)', [$date])
            ->where('tbltrans.Stat', 5)
            ->where('tblrate.isHousePlan', '<>', 0)
            ->where('tblfoliomaster.isAdditional', 0)
            ->where('tblfoliomaster.isSharer', 0)
            ->join('tbltariff', 'tbltariff.tariffID', '=', 'tbltrans.tariffID')
            ->join('tblrate', 'tblrate.RateID', '=', 'tbltariff.rateid')
            ->join('tblroom', 'tblroom.roomNo', '=', 'tbltrans.roomNo')
            ->join('tblroomtype', 'tblroomtype.roomTypeID', '=', 'tblroom.roomTypeID')
            ->join('tblfoliomaster', 'tblfoliomaster.mNoTrans', '=', 'tbltrans.NoTrans')
            ->get();

        $Adult = 0;
        if ($rows->isNotEmpty()) {
            foreach ($rows as $r) {
                $Adult += isset($r->Adult) ? (int)$r->Adult : 0;
            }
        }

        return $rows;
    }


    /**
     * Get house use records by date range
     *
     * @param string $fromDt
     * @param string $toDt
     * @param string $inRoomType
     */
    public static function getHouseUseByDate(
        string $fromDt,
        string $toDt,
        string $inRoomType = ""
    ) {
        // First query: In-house records
        $firstQuery = DB::table('tblinhouse as ih')
            ->select(
                'ih.iNoTrans as NoTrans',
                'ih.iTransDate as stayDate',
                'r.roomTypeID'
            )
            ->join('tblrate as rt', 'ih.iRateID', '=', 'rt.rateid')
            ->join('tblroom as r', 'r.roomNo', '=', 'ih.iRoomNo')
            ->whereDate('ih.iTransDate', '>=', $fromDt)
            ->whereDate('ih.iTransDate', '<=', $toDt)
            ->where('rt.isHousePlan', '<>', 0);

        // Add room type filter if provided
        if (!empty($inRoomType)) {
            $roomTypes = explode(',', $inRoomType);
            $firstQuery->whereIn('r.roomTypeID', $roomTypes);
        }

        // Second query: Reservation records
        $secondQuery = DB::table('tblreservation as res')
            ->select(
                'res.NoReserv as NoTrans',
                'rsn.stayDate as stayDate',
                'res.roomTypeID'
            )
            ->join('tblroomstocknew as rsn', 'res.NoReserv', '=', 'rsn.NoTrans')
            ->join('tblrate as rt', 'res.rateid', '=', 'rt.rateid')
            ->whereDate('rsn.stayDate', '>=', $fromDt)
            ->whereDate('rsn.stayDate', '<=', $toDt)
            ->where('rt.isHousePlan', '<>', 0)
            ->whereDate('rsn.stayDate', '<', DB::raw('date(res.CheckoutDate)'))
            ->whereNotIn('res.Stat', [0, 4, 7, 8]);

        // Add room type filter if provided
        if (!empty($inRoomType)) {
            $roomTypes = explode(',', $inRoomType);
            $secondQuery->whereIn('res.roomTypeID', $roomTypes);
        }

        // Combine both queries using UNION ALL
        return $firstQuery->unionAll($secondQuery)->get();
    }

    //Check room stock availability for a given room type and date range
    /**
     * Cek ketersediaan stok kamar untuk tipe kamar dan rentang tanggal tertentu (arrival-depart)
     * @param int $roomTypeID
     * @param int $roomNo
     * @param \DateTime $arrival
     * @param \DateTime $depart
     * @param string $NotNoTrans
     * @param bool $isHourly
     * @return bool
     */
    public function isRoomStockAvailable2020(
        int $roomTypeID,
        int $roomNo,
        \DateTime $arrival,
        \DateTime $depart,
        &$onDate,
        string $NotNoTrans = '',
        bool $isHourly = false
    ): bool {

        // ⏳ Koreksi tanggal jika bukan hourly
        if (!$isHourly && $arrival->format('Y-m-d') === $depart->format('Y-m-d')) {
            $depart->modify('+1 day');
        }

        // ⛔ Koreksi tanggal jika sebelum default aktif
        $defaultActiDate = Carbon::parse(session('activeDate'));
        if ($arrival < new \DateTime($defaultActiDate)) {
            $arrival = new \DateTime($defaultActiDate);
        }

        // 📦 Hitung stok kamar aktif
        $rstok = DB::table('tblroom')
            ->where('roomNo', '<>', 0)
            ->where('statusID', '<>', 1)
            ->whereNull('mergeTo')
            ->when($roomTypeID !== 0, fn($q) => $q->where('roomTypeID', $roomTypeID))
            ->count();

        if ($isHourly) {
            // Tambahkan logika hourly jika diperlukan
            return false;
        }

        $arrivalStr = $arrival->format('Y-m-d');
        $departStr = $depart->modify('-1 day')->format('Y-m-d');

        // 🔍 Query gabungan stok, blocking, dan multiroomtype
        $query = DB::table(DB::raw('(
            SELECT SUM(isStay) AS Stay, stayDate FROM tblroomstocknew
            WHERE roomtypeid = ' . $roomTypeID .
            ($NotNoTrans ? " AND NoTrans <> '$NotNoTrans'" : '') . '
            GROUP BY stayDate

            UNION ALL

            SELECT tblblockingd.Block AS Stay, DATE(tblblockingd.dDate) AS stayDate
            FROM tblblockingd
            INNER JOIN tblblocking ON tblblockingd.NoTransBlock = tblblocking.NoTransBlock
            WHERE statBlock = 2 AND roomTypeID = ' . $roomTypeID . '

            UNION ALL

            SELECT 1 AS Stay, DATE(tblmultiroomtype.OnDate) AS stayDate
            FROM tblmultiroomtype
            WHERE mStat = 1 AND roomTypeID = ' . $roomTypeID . '
        ) AS Qry'))
            ->select(DB::raw('SUM(Stay) AS Stay'), 'stayDate')
            ->whereBetween('stayDate', [$arrivalStr, $departStr])
            ->groupBy('stayDate')
            ->havingRaw("SUM(Stay) >= $rstok")
            ->get();

        if ($query->count() > 0) {
            $onDate = new \DateTime($query[0]->stayDate);
            return false;
        }

        // 🔍 Cek reservasi aktif
        if ($roomNo !== 0) {
            $reservationQuery = DB::table('tblroomstocknew')
                ->join('tblreservation', 'tblreservation.NoReserv', '=', 'tblroomstocknew.NoTrans')
                ->select('tblroomstocknew.NoTrans as t', 'stayDate')
                ->where('tblroomstocknew.roomtypeid', '<>', 10099)
                ->where('tblroomstocknew.roomNo', $roomNo)
                ->whereBetween('stayDate', [$arrivalStr, $departStr])
                ->where('isStay', '<>', 0)
                ->whereNotIn('tblreservation.Stat', [0, 4, 7, 8])
                ->when($roomTypeID !== 0, fn($q) => $q->where('tblroomstocknew.roomtypeid', $roomTypeID))
                ->when($NotNoTrans, fn($q) => $q->where('tblroomstocknew.NoTrans', '<>', $NotNoTrans))
                ->orderBy('stayDate')
                ->get();

            if ($reservationQuery->count() > 0 && !empty($reservationQuery[0]->stayDate)) {
                $onDate = new \DateTime($reservationQuery[0]->stayDate);
                return false;
            }

            // 🔍 Cek transaksi umum
            $transQuery = DB::table('tblroomstocknew')
                ->join('tbltrans', 'tbltrans.NoTrans', '=', 'tblroomstocknew.NoTrans')
                ->select('tblroomstocknew.NoTrans as t', 'stayDate')
                ->where('tblroomstocknew.roomtypeid', '<>', 190019)
                ->where('tblroomstocknew.roomNo', $roomNo)
                ->whereBetween('stayDate', [$arrivalStr, $depart->format('Y-m-d')])
                ->where('isStay', '<>', 0)
                ->where('tbltrans.stat', 5)
                ->whereRaw('stayDate <> DATE(tbltrans.ArrivalDate)')
                ->when($roomTypeID !== 0, fn($q) => $q->where('tblroomstocknew.roomtypeid', $roomTypeID))
                ->when($NotNoTrans, fn($q) => $q->where('tblroomstocknew.NoTrans', '<>', $NotNoTrans))
                ->orderBy('stayDate')
                ->get();

            if ($transQuery->count() > 0 && !empty($transQuery[0]->stayDate)) {
                $onDate = new \DateTime($transQuery[0]->stayDate);
                return false;
            }
        }

        return true;
    }

    /**
     * Dapatkan nilai tukar mata uang
     *
     * @param string $ccyID
     * @param float $amount
     * @param float $exchange
     * @param bool $isReverse
     * @param int $hitung
     * @return float
     */
    public function getExchangeRate(
        string $ccyID,
        float $amount,
        float $exchange,
        bool $isReverse = false,
        int $hitung = 0
    ): float {
        // 🏦 Default ke mata uang dasar jika kosong
        $baseCurrency = session('baseCurrency');
        if (empty($ccyID)) {
            $ccyID = $baseCurrency;
        }

        // 💵 Jika mata uang sama, tidak perlu konversi
        if ($ccyID === $baseCurrency) {
            return $amount;
        }

        // 🔍 Ambil metode perhitungan jika belum diberikan
        if ($hitung === 0) {
            $hitung = DB::table('tblcurrency')
                ->where('CurrencyID', $ccyID)
                ->value('Hitung') ?? 0;
        }

        // 🔄 Hitung nilai tukar
        try {
            if (!$isReverse) {
                // Konversi normal
                return $hitung === 0 ? $amount / $exchange : $amount * $exchange;
            } else {
                // Konversi balik
                return $hitung === 0 ? $amount * $exchange : $amount / $exchange;
            }
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function getExchangeRateDate(
        string $ccyID,
        \DateTime $transDate,
        float $amount,
        float &$exchange = 1,
        int &$rhitung = 0,
        bool $isReverse = false,
        bool $useMyRate = false
    ): float {
        // 🏦 Default ke mata uang dasar
        $baseCurrency = session('baseCurrency');
        if ($ccyID === $baseCurrency) {
            $exchange = 1;
            $rhitung = 0;
            return $amount;
        }

        // 🔍 Ambil kurs terbaru sebelum atau sama dengan tanggal transaksi
        $rateRow = DB::table('tblexchangerate')
            ->select('MidRate')
            ->whereDate('DateID', '<=', $transDate->format('Y-m-d'))
            ->where('CurrencyID', $ccyID)
            ->orderByDesc('DateID')
            ->limit(1)
            ->first();

        if ($rateRow) {
            // 🔢 Ambil metode perhitungan (0 = bagi, 1 = kali)
            $hitung = DB::table('tblcurrency')
                ->where('CurrencyID', $ccyID)
                ->value('Hitung') ?? 0;

            $rhitung = $hitung;
            $exc = $useMyRate ? $exchange : (float) $rateRow->MidRate;

            try {
                if (!$isReverse) {
                    $ttl = $hitung === 0 ? $amount / $exc : $amount * $exc;
                } else {
                    $ttl = $hitung === 0 ? $amount * $exc : $amount / $exc;
                }
            } catch (\Exception $e) {
                $ttl = 0.0;
            }

            $exchange = $exc;
            return $ttl;
        }

        // ❌ Kurs tidak ditemukan, gunakan default
        $exchange = 1;
        return $amount;
    }

    public static function insertNewFolioMaster(
        string $noTrans,
        string &$folioNo = '',
        bool $isAdditional = false,
        bool $isSharer = false,
        int $countryID = 101,
        string $defFolioNo = ''
    ): bool {
        // Generate Folio Number
        $cgen = new CodeGeneratorService();
        $folioNo = $cgen->autoGenerateCode(
            "tblfoliomaster",
            TransType::ExtraCharge->value,
            '',
            '',
            false,
            $countryID,
            '',
            '',
            false,
            false,
            $defFolioNo
        );

        // Prepare insert data
        $data = [
            "mFolioNo"        => $folioNo,
            "mNoTrans"        => $noTrans,
            "isAdditional"    => $isAdditional,
            "isSharer"        => $isSharer,
            "mOriNoTrans"     => $noTrans,
            "mOriCreatedOn"   => now(),
            "mOriDescription" => 'By ' . (auth()->user()->name ?? 'System') .
                ' (' . (auth()->id() ?? '0') . ')@' . gethostname() .
                '. No Trans: ' . $noTrans,
        ];

        // Insert into database
        return DB::table("tblfoliomaster")->insert($data);
    }

    public static function getRoomOnHand(
        string $fromDt,
        string $toDt,
        bool $isOO = false,
        bool $incTentative = true,
        bool $isOOO = false
    ) {
        $queries = [];

        if (!$isOO && !$isOOO) {
            // IN HOUSE block
            $inHouseQuery = DB::table('tblroomstocknew as rs')
                ->selectRaw("
                    fm.mFolioNo as FolioNo,
                    fm.mNoTrans as NoTrans,
                    rs.roomNo,
                    rt.roomTypeCode,
                    concat_ws(' ', t.tGuestName, t.tGuestLastName) as GuestName,
                    t.ArrivalDate,
                    t.DepartDate,
                    c.CompName,
                    rs.isReservation,
                    rs.stayDate,
                    IFNULL((
                        SELECT amount FROM tbltransd 
                        WHERE NoTrans = t.NoTrans 
                        AND DATE(transDate) = ? 
                        AND ChargeTypeID = 1 
                        AND NoTransD LIKE 'TDR%' 
                        AND Stat > 4 
                        LIMIT 1
                    ), t.TotalNet) as TotalNET,
                    'IN HOUSE' as Status,
                    ct.typeName,
                    s.segmentName,
                    f.FolioType,
                    t.Adult as Pax,
                    IF(DATEDIFF(t.DepartDate, t.ArrivalDate) - 1 = 0, 1, DATEDIFF(t.DepartDate, t.ArrivalDate)) as Night,
                    r.isComplimentary,
                    r.isHousePlan,
                    IF(t.isDayUse <> 0 OR t.isDayUseCHK <> 0, 1, 0) as isDayUse,
                    t.Stat as StatusID
                ", [$fromDt])
                ->join('tbltrans as t', 't.NoTrans', '=', 'rs.NoTrans')
                ->join('tblfoliomaster as fm', 'fm.mNoTrans', '=', 't.NoTrans')
                ->join('tblroomtype as rt', 'rt.roomTypeID', '=', 'rs.roomTypeID')
                ->join('tblcompany as c', 'c.CompID', '=', 't.tCompID')
                ->join('tblcompanytype as ct', 'ct.cTypeID', '=', 'c.cTypeID')
                ->join('tblfolio as f', 'f.FolioNo', '=', 'fm.mFolioNo')
                ->join('tbltariff as tf', 'tf.tariffID', '=', 't.tariffID')
                ->join('tblrate as r', 'r.RateID', '=', 'tf.rateid')
                ->join('tblsegment as s', 's.segmentID', '=', 't.segmentID')
                ->whereBetween(DB::raw('DATE(rs.stayDate)'), [$fromDt, $toDt])
                ->where('fm.isAdditional', 0)
                ->where('fm.isSharer', 0)
                ->whereRaw('DATE(rs.stayDate) < DATE(t.DepartDate)');

            $queries[] = $inHouseQuery;

            // RESERVATION block
            $reservationQuery = DB::table('tblroomstocknew as rs')
                ->selectRaw("
                    fm.mFolioNo as FolioNo,
                    fm.mNoTrans as NoTrans,
                    rs.roomNo,
                    rt.roomTypeCode,
                    concat_ws(' ', tr.tGuestName, tr.tGuestLastName) as GuestName,
                    tr.ArrivalDate,
                    tr.DepartDate,
                    c.CompName,
                    rs.isReservation,
                    rs.stayDate,
                    IFNULL((
                        SELECT amount FROM tbltransdreserv 
                        WHERE NoTrans = tr.NoTrans 
                        AND DATE(transDate) = ? 
                        AND ChargeTypeID = 1 
                        AND NoTransD LIKE 'RTDR%' 
                        AND Stat > 4 
                        LIMIT 1
                    ), tr.total) as TotalNET,
                    'RESERVATION' as Status,
                    ct.typeName,
                    s.segmentName,
                    IF(tr.noMice IS NULL, 'PA', IF(tr.isMasterGroup, 'MF', 'GF')) as FolioType,
                    tr.Adult as Pax,
                    IF(DATEDIFF(tr.DepartDate, tr.ArrivalDate) - 1 = 0, 1, DATEDIFF(tr.DepartDate, tr.ArrivalDate)) as Night,
                    r.isComplimentary,
                    r.isHousePlan,
                    0 as isDayUse,
                    res.Stat as StatusID
                ", [$fromDt])
                ->join('tbltransreserv as tr', 'tr.NoTrans', '=', 'rs.NoTrans')
                ->join('tblfoliomaster as fm', 'fm.mNoTrans', '=', 'tr.NoTrans')
                ->join('tblroomtype as rt', 'rt.roomTypeID', '=', 'rs.roomTypeID')
                ->join('tblcompany as c', 'c.CompID', '=', 'tr.tCompID')
                ->join('tblcompanytype as ct', 'ct.cTypeID', '=', 'c.cTypeID')
                ->join('tblsegment as s', 's.segmentID', '=', 'tr.segmentID')
                ->join('tbltariff as tf', 'tf.tariffID', '=', 'tr.tariffID')
                ->join('tblrate as r', 'r.RateID', '=', 'tf.rateid')
                ->join('tblreservation as res', 'res.NoReserv', '=', 'tr.NoTrans')
                ->whereBetween(DB::raw('DATE(rs.stayDate)'), [$fromDt, $toDt])
                ->whereRaw('DATE(rs.stayDate) < DATE(tr.DepartDate)');

            if (!$incTentative) {
                $reservationQuery->where('res.Stat', '>', 1);
            }

            $queries[] = $reservationQuery;
        } else {
            // OUT OF INVENTORY / OUT OF SERVICE block
            $statusLabel = $isOO ? 'OUT OF INVENTORY' : 'OUT OF SERVICE';
            $statusField = $isOO ? 'isOO' : 'isOOO';
            $startField = $isOO ? 'statusStart' : 'roomOOOStart';
            $endField = $isOO ? 'statusEnd' : 'roomOOOEnd';
            $remarkField = $isOO ? 'statusRemark' : 'roomOOORemark';

            $ooQuery = DB::table('tblroomstocknew as rs')
                ->selectRaw("
                    '' as FolioNo,
                    '' as NoTrans,
                    rs.roomNo,
                    rt.roomTypeCode,
                    '-' as GuestName,
                    r.$startField as ArrivalDate,
                    r.$endField as DepartDate,
                    r.$remarkField as statusRemark,
                    rs.isReservation,
                    rs.stayDate,
                    0 as TotalNET,
                    '$statusLabel' as Status
                ")
                ->join('tblroom as r', 'r.roomNo', '=', 'rs.roomNo')
                ->join('tblroomtype as rt', 'rt.roomTypeID', '=', 'r.roomTypeID')
                ->whereBetween(DB::raw('DATE(rs.stayDate)'), [$fromDt, $toDt])
                ->where("rs.$statusField", '<>', 0);

            $queries[] = $ooQuery;
        }

        // Combine all queries using UNION ALL
        $finalQuery = array_shift($queries);
        foreach ($queries as $q) {
            $finalQuery->unionAll($q);
        }

        return $finalQuery->get();
    }
    public static function getCountTransReserv(string $noReserv): float
    {
        return DB::table('tbltransreserv as tr')
            ->join('tbltransdreserv as td', 'td.NoTrans', '=', 'tr.NoTrans')
            ->where('tr.tReservNo', $noReserv)
            ->where('td.stat', '>', 5)
            ->sum('td.oriamount') ?? 0.0;
    }

    public static function insertInHouseForNewTransD(string $NoTransD, int $stat = 0, int $newTariffID = 0, int $roomNo = 0): bool
    {
        // Step 1: Build the base query
        $query = DB::table('tbltrans')
            ->select([
                'tbltrans.NoTrans',
                'tbltrans.ArrivalDate',
                'tbltrans.DepartDate',
                'tbltrans.tBusID',
                'tbltrans.tCompID',
                'tbltrans.clientID',
                'tbltrans.TotalNETOri',
                'tbltrans.tariffID',
                'tbltrans.checkoutDate',
                'tbltrans.oriamount',
                'tbltrans.amount',
                'tbltransd.transDate',
                'tbltrans.roomNo as roomNoD',
                'tbltransd.FolioNo',
                'tbltariff.rateid',
                'tbltrans.segmentID',
                'tbltrans.segmentIDSub',
                'tbltransd.adult',
                'tbltransd.child',
                'tbltrans.CurrencyID',
                'tbltransd.NoTransD',
                'tbltransd.Exchange',
                'tbltransclient.CountryID',
                'tbltrans.isHouse'
            ])
            ->join('tbltransd', 'tbltransd.NoTrans', '=', 'tbltrans.NoTrans')
            ->join('tbltariff', 'tbltariff.tariffID', '=', 'tbltrans.tariffID')
            ->where('tbltransd.NoTransD', $NoTransD);

        // Conditional join and where
        if ($stat !== 0) {
            $query->where('tbltrans.Stat', $stat)
                ->leftJoin('tbltransclient', 'tbltransclient.NoTrans', '=', 'tbltrans.NoTrans');
        } else {
            $query->where('tbltrans.Stat', 5)
                ->join('tbltransclient', 'tbltransclient.NoTrans', '=', 'tbltrans.NoTrans');
        }

        $dt = $query->limit(1)->get();

        if ($dt->isEmpty() || is_null($dt[0]->NoTrans)) {
            return true;
        }

        $row = $dt[0];

        // Step 2: Check isHouse and folio conflict
        if ($row->isHouse) {
            $existingFolio = DB::table('tblinhouse')
                ->where('iNoTransD', $NoTransD)
                ->value('iFolioNo');

            if ($existingFolio !== $row->FolioNo) {
                return true;
            }
        }

        // Step 3: Delete existing entry
        DB::table('tblinhouse')->where('iNoTransD', $NoTransD)->delete();

        // Step 4: Determine CountryID
        $ctrID = $row->CountryID ?? DB::table('tblclient')
            ->where('clientID', $row->clientID)
            ->value('CountryID') ?? 101;

        // Step 5: Insert new record
        $insertData = [
            'iNoTrans'        => $row->NoTrans,
            'iTransDate'      => $row->transDate,
            'iBusID'          => $row->tBusID,
            'iClientID'       => $row->clientID,
            'iCompID'         => $row->tCompID,
            'iCountryID'      => $ctrID,
            'iRoomRate'       => $row->oriamount,
            'iRoomRateIDR'    => $row->amount,
            'iTariffID'       => $newTariffID ?: $row->tariffID,
            'iCurrencyID'     => $row->CurrencyID,
            'iExchange'       => $row->Exchange,
            'iRoomNo'         => $roomNo ?: $row->roomNoD,
            'iFolioNo'        => $row->FolioNo,
            'iRateID'         => $row->rateid,
            'iNoTransD'       => $row->NoTransD,
            'iAdult'          => $row->adult,
            'iChild'          => $row->child,
            'iSegmentID'      => $row->segmentID,
            'iSegmentIDSub'   => $row->segmentIDSub,
        ];

        try {
            DB::table('tblinhouse')->insertOrIgnore($insertData);
            return true;
        } catch (\Exception $e) {
            Log::error('insertInHouseForNewTransD failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function listReservationProfile(string $NoReserv)
    {

        $result = DB::table('tblreservation as g')
            ->select([
                'Balance',
                'CheckInDate',
                'g.CheckOutDate',
                DB::raw("DATE_FORMAT(CheckInDate, '%Y%m%d') AS inDateStr"),
                DB::raw("DATE_FORMAT(g.CheckOutDate, '%Y%m%d') AS outDateStr"),
                'g.ClientID',
                'DateReserv',
                'Discount',
                'g.FolioNo',
                'g.NoReserv',
                'rateComp',
                'Remark1',
                'Remark2',
                'rEmail',
                DB::raw("IF(isWaitingList = 0, 'No', 'Yes') AS waitStr"),
                'isWaitingList',
                'g.roomNo',
                'roomRate',
                'g.child',
                'g.Infant',
                'g.Adult',
                DB::raw("CONCAT(g.Adult, '/', g.child) AS Pax"),
                'g.tariffID',
                'g.rateid',
                'g.CreatedBy',
                'g.CreatedOn',
                'g.UpdatedBy',
                'g.UpdatedOn',
                'g.roomTypeID',
                'roomType',
                'roomTypeCode',
                'g.Stat',
                DB::raw("IF(isNoShow <> 0, 'No Show', StatName) AS StatName"),
                'rAddress as Address',
                'rFirstName as FirstName',
                'rLastName as LastName',
                DB::raw("CONCAT_WS(' ', rFirstName, rLastName) AS GuestName"),
                'BirthCity',
                'rBusID as busID',
                'rCity as City',
                'rClientType as ClientType',
                'rCountryID as CountryID',
                'BirthDate',
                'rEmail as Email',
                'rFax',
                'rPhone',
                'rPostal',
                'rIDType as TypeID',
                'rIDExp as TypeIDExpDate',
                'rIDNumber as TypeIDNumber',
                'rIDRel as TypeIDRelDate',
                'rMobile',
                'rVIP',
                'rCutOnDate',
                'channelCode',
                'isNoShowAuto',
                'g.SegmentIDSub',
                DB::raw("(SELECT tariffComp FROM tbltariffcompany WHERE tariffID = g.tariffID AND CompID = g.rCompID AND DATE(startDate) <= DATE(g.CheckInDate) AND DATE(endDate) >= DATE(g.CheckInDate)) AS CompRate"),
                DB::raw("IFNULL(rCompName, CompName) AS CompName"),
                'tblcompany.CompID',
                'tblcompany.compGroupID',
                'g.segmentID',
                'g.CityID',
                'g.DestCityID',
                DB::raw("IF(tblbusinesssource.busID IS NULL, '', CONCAT_WS(' - ', busName, busRemark)) AS BusSource"),
                'rCompID',
                'segmentName',
                'RateName',
                'rCurrencyID',
                'rAlreadyUpdated',
                'g.NoMice',
                'tbltransclientreserv.VehicleID',
                'VehicleColor',
                'VehiclePlatNo',
                'vehicleModel',
                'vehicleMake',
                'vessIn',
                'vessInTime',
                'vessOut',
                'vessOutTime',
                'vessPlatNo',
                'vessRemark',
                'vessRemark1',
                'DriverName',
                'sText',
                'FBGuestOrder',
                'infFrom',
                'infTo',
                'harbourIn',
                'harbourOut',
                'RemarkPOS',
                'isNoShow',
                'BookingID',
                'rIsMasterGroup',
                'Bed',
                'xtraBed',
                'rGuestTitle',
                'rVoucherNo',
                'rCCNo',
                'rCCValid',
                'rContact',
                'rCreditLimit',
                'rCITime',
                'rCITimeLimit',
                'rCOTime',
                'rMemberID',
                'rCompName',
                'rCreditLimitPayID',
                'rAddress',
                'rIDNumber',
                'rIDType',
                'rCountryID',
                'rPhone',
                'rLateCOCharge',
                'rUseLateCO',
                'rOldRoomTypeID',
                'rIsPickup',
                DB::raw("IF(g.roomno = 0, '-', IF(tblroom.statusID = 4, 'OOI', IF(isRoomOOO <> 0, 'OOS', IF(isHold <> 0, 'HOLD', ShortName)))) AS ShortName"),
                DB::raw("IF(g.roomno = 0, '-', IF(isRoomOOO <> 0, 'OOS', IF(isHold <> 0, IF(tblroom.statusID = 5, CONCAT('VD', '/', 'HOLD'), IF(tblroom.statusID = 6, CONCAT('VCR', '/', 'HOLD'), 'HOLD')), IF(tblroomstatus.statusName <> 'Out of Inventory', CONCAT(SUBSTRING(TRIM(SUBSTRING_INDEX(tblroomstatus.statusName, ' ', 1)), 1, 1), '', SUBSTRING(TRIM(SUBSTRING_INDEX(tblroomstatus.statusName, ' ', -1)), 1, 1)), 'OOI')))) AS RoomStatus"),
                'isHold',
                'isRoomOOO',
                'isInspect',
                'tblroom.statusID',
                'isHasDepositPaid',
                'isHasDepositNotPaid',
                'isHasDepositBox',
                'isHasDepositReq',
                'isHasLockedRoom',
                'isHasHoneyMoon',
                'isHasCashOnlyNOCF',
                'isHasHouseUse',
                'isHasRepeatGuest',
                'isHasXBed',
                'isHasLongStay',
                'isHasForeigner',
                'isHasVIP1',
                'isHasVIP2',
                'isHasVIP3',
                'isHasVVIP',
                'isHasWalkIn',
                'isHasCashBasis',
                'isHasInternetReservation',
                'isHasEarlyCheckin',
                'isHasUpgradedRoomType',
                'isHasRoomMove',
                'isHasMessage',
                'tblrateschedulereserv.NoReserv AS NoReservSchedule',
                'tblcustomrate.cRateID',
                'tbltransreserv.xtraBedNight',
                'tbluser.username AS CreatedByUser',
                'u2.username AS UpdatedByUser',
                'g.rLanguage AS Language',
                'g.isAPT',
                'nUrutOTA',
                'rciBy',
                'rciOn',
                'rHourlyRateID',
                'rHourlySessionID',
                'rHourlyStart',
                'rHourlyEnd'
            ])
            ->join('tblroomtype', 'tblroomtype.roomTypeID', '=', 'g.roomTypeID')
            ->join('tbltransclientreserv', 'tbltransclientreserv.NoTrans', '=', 'g.NoReserv')
            ->join('tblvehicle', 'tblvehicle.vehicleID', '=', 'tbltransclientreserv.VehicleID')
            ->join('tblstatusreservation', 'tblstatusreservation.StatID', '=', 'g.Stat')
            ->join('tblrate', 'tblrate.rateid', '=', 'g.rateid')
            ->join('tblsegment', 'tblsegment.segmentID', '=', 'g.segmentID')
            ->leftJoin('tblcompany', 'tblcompany.CompID', '=', 'g.rCompID')
            ->join('tbltransreserv', 'tbltransreserv.NoTrans', '=', 'g.NoReserv')
            ->join('tblroom', 'tblroom.roomNo', '=', 'g.roomNo')
            ->join('tblroomstatus', 'tblroomstatus.StatusID', '=', 'tblroom.statusID')
            ->leftJoin('tblbusinesssource', 'tblbusinesssource.busID', '=', 'g.rBusID')
            ->leftJoin('tbltranslegend', 'tbltranslegend.FolioNo', '=', 'g.FolioNo')
            ->leftJoin('tblrateschedulereserv', 'tblrateschedulereserv.NoReserv', '=', 'g.NoReserv')
            ->leftJoin('tblcustomrate', 'tblcustomrate.NoTrans', '=', 'g.NoReserv')
            ->join('tbluser', 'tbluser.userid', '=', 'g.CreatedBy')
            ->leftJoin('tbluser as u2', 'u2.userid', '=', 'g.UpdatedBy')
            ->where('g.Stat', '<>', 0)
            ->where('g.rIsMasterGroup', '=', 0)
            ->where('g.NoReserv', '=', $NoReserv)
            ->first();

        return $result;
    }

    /**
     * Hitung total kamar sesuai roomType dan optional floor
     *
     * @param int $roomTypeID
     * @param int $floorID
     * @return int
     */
    public static function getTotalRoom(int $roomTypeID, int $floorID = 0): int
    {
        $qb = DB::table('tblroom')
            ->join('tblroomtype', 'tblroomtype.roomTypeID', '=', 'tblroom.roomTypeID')
            ->where('tblroom.roomNo', '<>', 0)
            ->where('tblroom.statusID', '<>', 1)
            ->where('tblroomtype.isTypeActive', '<>', 0);

        if ($roomTypeID !== 0) {
            $qb->where('tblroom.roomTypeID', $roomTypeID);
        }

        if ($floorID !== 0) {
            $qb->where('tblroom.floorID', $floorID);
        }

        $count = (int) $qb->count('tblroom.roomNo');

        return $count;
    }

    public static function getTotalRoomRateInDateNew($fromDt, $toDt, $isByFloor = false, $isBonafide = false)
    {
        $fromDate = Carbon::parse($fromDt)->format('Y-m-d');
        $toDate = Carbon::parse($toDt)->format('Y-m-d');

        // First SELECT block
        $query1 = DB::table('tbltransd as g')
            ->selectRaw("
                DATE(g.transDate) AS transDate,
                tblroom.roomTypeID AS roomTypeID,
                SUM(
                    IF(g.isPostMinus = 1, 0, IF(tbltransdtariff.JumlahEqv < 0, 0, tbltransdtariff.JumlahEqv)) -
                    IF(g.isPostMinus = 1, tbltransdtariff.JumlahEqv * -1, IF(tbltransdtariff.JumlahEqv < 0, tbltransdtariff.JumlahEqv * -1, 0))
                ) AS totalRate,
                SUM(
                    IF(g.isPostMinus = 1, 0, IF(tbltransdtariff.BaseAmt < 0, 0, tbltransdtariff.BaseAmt)) -
                    IF(g.isPostMinus = 1, tbltransdtariff.BaseAmt * -1, IF(tbltransdtariff.BaseAmt < 0, tbltransdtariff.BaseAmt * -1, 0))
                ) AS totalRateBase,
                tblroom.floorID AS floorID
            ")
            ->join('tblroom', 'tblroom.roomNo', '=', 'g.roomNoD')
            ->join('tbltrans', 'tbltrans.NoTrans', '=', 'g.NoTrans')
            ->join('tbltransdtariff', 'tbltransdtariff.NoTransD', '=', 'g.NoTransD')
            ->where('g.transaksiID', 1)
            ->where('tbltransdtariff.ChargeTypeID', 1)
            ->where('g.stat', '>', 4)
            ->where('isVoid', 0)
            ->whereBetween(DB::raw('DATE(g.transDate)'), [$fromDate, $toDate]);

        if ($isBonafide) {
            $query1->where('tbltrans.Stat', '>=', 5);
        } else {
            $query1->where('tbltrans.Stat', '=', 5);
        }

        $groupBy1 = ['transDate', 'tblroom.roomTypeID'];
        if ($isByFloor) {
            $groupBy1[] = 'tblroom.floorID';
        }
        $query1->groupBy($groupBy1);

        // Second SELECT block
        $query2 = DB::table('tblreservation')
            ->selectRaw("
                DATE(tbltransdreserv.transDate) AS transDate,
                tblreservation.roomTypeID AS roomTypeID,
                SUM(tbltransdtariffreserv.JumlahEqv) AS totalRate,
                SUM(tbltransdtariffreserv.BaseAmt) AS totalRateBase,
                tblroom.floorID AS floorID
            ")
            ->join('tbltransdreserv', 'tbltransdreserv.NoTrans', '=', 'tblreservation.NoReserv')
            ->join('tblroom', 'tblroom.roomNo', '=', 'tblreservation.roomNo')
            ->join('tbltransdtariffreserv', 'tbltransdtariffreserv.NoTransD', '=', 'tbltransdreserv.NoTransD')
            ->whereNotIn('tblreservation.Stat', [0, 4, 7, 8])
            ->where('tbltransdreserv.Stat', '>', 4)
            ->where('tbltransdtariffreserv.ChargeTypeID', 1)
            ->where('tblreservation.isWaitingList', 0)
            ->where('tblreservation.rIsMasterGroup', 0)
            ->where('isVoid', 0)
            ->whereBetween(DB::raw('DATE(tbltransdreserv.transDate)'), [$fromDate, $toDate]);

        $groupBy2 = ['transDate', 'tblreservation.roomTypeID'];
        if ($isByFloor) {
            $groupBy2[] = 'tblroom.floorID';
        }
        $query2->groupBy($groupBy2);

        // Combine both queries using UNION ALL
        $sql1 = $query1->toSql();
        $sql2 = $query2->toSql();

        $bindings = array_merge($query1->getBindings(), $query2->getBindings());

        $unionSql = "($sql1) UNION ALL ($sql2)";

        $results = DB::select($unionSql, $bindings);

        return collect($results);
    }

    public static function getOOByDateRoomStockInventory($fromDt, $toDt, $inRoomTypeID = null)
    {
        $fromDate = Carbon::parse($fromDt)->format('Y-m-d');
        $toDate = Carbon::parse($toDt)->format('Y-m-d');

        // Build the query
        $query = DB::table('tblroomstocknew')
            ->select([
                'tblooolist.fromDate as statusStart',
                'tblooolist.toDate as statusEnd',
                'tblroomstocknew.stayDate',
                'tblroomstocknew.roomNo',
                'tblooolist.roomTypeID'
            ])
            ->join('tblooolist', 'tblooolist.roomNo', '=', 'tblroomstocknew.roomNo')
            ->where('tblroomstocknew.isOO', 1)
            ->whereBetween(DB::raw('DATE(tblroomstocknew.stayDate)'), [$fromDate, $toDate]);

        if (!empty($inRoomTypeID)) {
            $roomTypeIDs = explode(',', $inRoomTypeID);
            $query->whereIn('tblroomstocknew.roomTypeID', $roomTypeIDs);
        }

        $results = $query->get();

        // Filter: stayDate < statusEnd
        $filtered = $results->filter(function ($row) {
            return Carbon::parse($row->stayDate)->lt(Carbon::parse($row->statusEnd));
        });

        // Format as array of rows (like DataTable)
        $formatted = $filtered->map(function ($row) {
            return [
                'statusStart'   => $row->statusStart,
                'statusEnd'     => $row->statusEnd,
                'stayDate'      => $row->stayDate,
                'roomNo'        => $row->roomNo,
                'roomTypeID'    => $row->roomTypeID
            ];
        });

        return collect($formatted);
    }

    public static function getOOOByDateRoomStockInventory($fromDt, $toDt, $inRoomTypeID = '', $greaterOrEqual = false)
    {
        $fromDate = Carbon::parse($fromDt)->format('Y-m-d');
        $toDate = Carbon::parse($toDt)->format('Y-m-d');

        $query = DB::table('tblooolist')
            ->select([
                'tblooolist.fromDate as roomOOOStart',
                'tblooolist.toDate as roomOOOEnd',
                'tblooolist.roomNo',
                'tblooolist.roomTypeID'
            ])
            ->where('tblooolist.isOOO', '<>', 0)
            ->whereDate('tblooolist.fromDate', '<=', $toDate);

        if ($greaterOrEqual) {
            $query->whereDate('tblooolist.toDate', '>=', $fromDate);
        } else {
            $query->whereDate('tblooolist.toDate', '>', $fromDate);
        }

        if (!empty($inRoomTypeID)) {
            $roomTypeIDs = explode(',', $inRoomTypeID);
            $query->whereIn('tblooolist.roomTypeID', $roomTypeIDs);
        }

        // Execute query
        $results = $query->get();

        return collect($results);
    }

    public static function countBlockedRoomTypeinventory($INroomTypeID, $fromDt, $toDt)
    {
        $fromDate = Carbon::parse($fromDt)->format('Y-m-d');
        $toDate = Carbon::parse($toDt)->format('Y-m-d');

        $query = DB::table('tblblocking')
            ->selectRaw('SUM(tblblockingd.Block) AS Block')
            ->selectRaw('DATE(tblblockingd.dDate) AS dDate')
            ->addSelect('tblblockingd.roomTypeID')
            ->join('tblblockingd', 'tblblockingd.NoTransBlock', '=', 'tblblocking.NoTransBlock')
            ->whereBetween(DB::raw('DATE(tblblockingd.dDate)'), [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(tblblockingd.dDate)'), 'tblblockingd.roomTypeID');

        if ($INroomTypeID === '0') {
            $query->where('tblblocking.statBlock', '=', 2);
        } else {
            $roomTypeIDs = explode(',', $INroomTypeID);
            $query->whereIn('tblblockingd.roomTypeID', $roomTypeIDs)
                ->where('tblblocking.statBlock', '=', 2);
        }

        // Apply HAVING clause
        $query->havingRaw('SUM(tblblockingd.Block) <> 0');

        $results = $query->get();

        return collect($results);
    }

    public static function getTodayDayUseCHKCount()
    {
        $defaultActiDate = Carbon::parse(session('activeDate'));

        $results = DB::table('tbltrans')
            ->select([
                'tbltrans.NoTrans',
                'tblroom.roomTypeID',
                DB::raw("IF(tbltrans.Stat = 5, 1, 0) AS DUCOUNT"),
                DB::raw("IF(tbltrans.Stat > 5, 1, 0) AS DUCOUNTTRANS")
            ])
            ->join('tblroom', 'tblroom.roomNo', '=', 'tbltrans.roomNo')
            ->where('tbltrans.Stat', '>=', 5)
            ->where('tbltrans.isDayUseCHK', '<>', 0)
            ->where('tbltrans.roomNo', '<>', 0)
            ->whereRaw("DATE(IFNULL(tbltrans.checkoutDate, ?)) = DATE(?)", [$defaultActiDate, $defaultActiDate])
            ->get();

        return collect($results);
    }

    public static function getRoomTypeStock($roomTypeID, $strNotIn = '', $incHold = true)
    {
        $query = DB::table('tblroom')
            ->selectRaw('COUNT(tblroom.roomTypeID) AS cRoom')
            ->addSelect('tblroom.roomTypeID')
            ->whereNotIn('tblroom.statusID', [1])
            ->whereNull('tblroom.mergeTo');

        if ($roomTypeID != 0) {
            $query->where('tblroom.roomTypeID', $roomTypeID);
        }

        if (!empty($strNotIn)) {
            $roomNos = explode(',', $strNotIn);
            $query->whereNotIn('tblroom.roomNo', $roomNos);
        }

        if (!$incHold) {
            $query->where('tblroom.isHold', 0);
        }

        $query->groupBy('tblroom.roomTypeID');

        $results = $query->get();

        return collect($results);
    }

    public static function getCountTentativeReservByDateInventory($fromDt, $toDt, $statIN = null)
    {
        $fromDate = Carbon::parse($fromDt)->format('Y-m-d');
        $toDate = Carbon::parse($toDt)->format('Y-m-d');

        $query = DB::table('tblroomstocknew')
            ->select([
                'tblroomstocknew.NoTrans',
                'tblroomstocknew.roomTypeID',
                'tblroomstocknew.stayDate',
                'tblreservation.CheckOutDate',
                'tblreservation.Stat'
            ])
            ->join('tblreservation', 'tblreservation.NoReserv', '=', 'tblroomstocknew.NoTrans')
            ->whereDate('tblroomstocknew.stayDate', '>=', $fromDate)
            ->whereDate('tblroomstocknew.stayDate', '<=', $toDate)
            ->where('tblreservation.rIsMasterGroup', 0)
            ->where('tblreservation.isWaitingList', 0)
            ->whereRaw('DATE(tblroomstocknew.stayDate) < DATE(tblreservation.CheckOutDate)');

        if (empty($statIN)) {
            $query->where('tblreservation.Stat', 1);
        } else {
            $query->whereIn('tblreservation.Stat', $statIN);
        }

        $results = $query->get();

        return collect($results);
    }

    public static function getCountCompHU($fromDt, $toDt)
    {
        $fromDate = Carbon::parse($fromDt)->format('Y-m-d');
        $toDate = Carbon::parse($toDt)->format('Y-m-d');

        $sql = "
            SELECT
                g.roomTypeID,
                g.NoTrans,
                g.roomNo,
                isOO,
                isOOO,
                stayDate,
                Query1.MxDate,
                Query2.roomCount,
                mFolioNo,
                IF(isRateHU, 1, 0) AS isHU
            FROM tblroomstocknew g
            INNER JOIN (
                SELECT MAX(stayDate) AS MxDate, NoTrans, roomNo, roomTypeID
                FROM tblroomstocknew
                GROUP BY NoTrans, roomNo
            ) Query1 ON g.NoTrans = Query1.NoTrans AND g.roomNo = Query1.roomNo
            INNER JOIN (
                SELECT COUNT(roomNo) AS roomCount, roomTypeID
                FROM tblroom
                WHERE statusID <> 1 AND roomNo <> 0 AND mergeTo IS NULL
                GROUP BY roomTypeID
            ) Query2 ON Query2.roomTypeID = g.roomTypeID
            INNER JOIN tblroomtype ON tblroomtype.roomTypeID = g.roomTypeID
            INNER JOIN tbltrans ON tbltrans.NoTrans = g.NoTrans
            INNER JOIN tbltariff ON tbltariff.tariffID = tbltrans.tariffID
            INNER JOIN tblrate ON tblrate.rateid = tbltariff.rateid
            INNER JOIN tblfoliomaster ON tblfoliomaster.mNoTrans = tbltrans.NoTrans
            WHERE DATE(stayDate) >= DATE(?) AND DATE(stayDate) <= DATE(?)
            AND tblroomtype.isTypeActive <> 0
            AND g.isReservation = 0 AND g.isOO = 0 AND g.isOOO = 0
            AND isMerge = 0 AND (isRateHU <> 0 OR isRateCompl <> 0)
            AND g.isStay <> 0

            UNION ALL

            SELECT
                g.roomTypeID,
                g.NoTrans,
                g.roomNo,
                isOO,
                isOOO,
                stayDate,
                Query1.MxDate,
                Query2.roomCount,
                FolioNo,
                IF(isHousePlan, 1, 0) AS isHU
            FROM tblroomstocknew g
            INNER JOIN (
                SELECT MAX(stayDate) AS MxDate, NoTrans, roomNo, roomTypeID
                FROM tblroomstocknew
                GROUP BY NoTrans, roomNo
            ) Query1 ON g.NoTrans = Query1.NoTrans AND g.roomNo = Query1.roomNo
            INNER JOIN (
                SELECT COUNT(roomNo) AS roomCount, roomTypeID
                FROM tblroom
                WHERE statusID <> 1 AND roomNo <> 0 AND mergeTo IS NULL
                GROUP BY roomTypeID
            ) Query2 ON Query2.roomTypeID = g.roomTypeID
            INNER JOIN tblroomtype ON tblroomtype.roomTypeID = g.roomTypeID
            INNER JOIN tblreservation ON tblreservation.NoReserv = g.NoTrans
            INNER JOIN tblrate ON tblrate.rateid = tblreservation.rateid
            WHERE DATE(stayDate) >= DATE(?) AND DATE(stayDate) <= DATE(?)
            AND tblroomtype.isTypeActive <> 0
            AND g.isReservation <> 0 AND g.isOO = 0 AND g.isOOO = 0
            AND isMerge = 0 AND (isHousePlan <> 0 OR isComplimentary <> 0)
            AND g.isStay <> 0
        ";

        $bindings = [$fromDate, $toDate, $fromDate, $toDate];

        $results = DB::select($sql, $bindings);

        return collect($results);
    }

    public static function listXBedDateInv($fromDt)
    {
        $fromDate = Carbon::parse($fromDt)->format('Y-m-d');

        $results = DB::table('tblstockextrabed')
            ->select(['Stock', 'dateID'])
            ->where('isDeleted', 0)
            ->whereDate('dateID', '>=', $fromDate)
            ->orderBy('dateID', 'asc')
            ->get();

        return collect($results);
    }

    //Caculate occupancy for a given arrival date
    /**
     * Hitung okupansi untuk tanggal kedatangan tertentu
     * @param \DateTime $dtArrival
     * @param bool $includeHU
     * @return float
     */
    public function calculateOccupancy(\DateTime $dtArrival, bool $includeHU = true): float
    {
        $defaultActiDate = Carbon::parse(session('activeDate'));
        if ($dtArrival <= $defaultActiDate) {
            $totOO = $this->getOOCount($defaultActiDate, false, false, false, false);
            $totOCRV = count($this->getOccupiedRoomReservToday($defaultActiDate));
            $totOCNight = $this->listDashOccRoom($defaultActiDate);
            $ooEndToday = $this->getOOCountToday(); // OOI
            $HUCount = count($this->getHouseUse($defaultActiDate)->toArray());
            $tRoomT = $this->getTotalRoom(0);

            if ($includeHU) {
                $occ = (($totOCNight + $totOCRV) / ($tRoomT - ($totOO - $ooEndToday) - $HUCount)) * 100;
            } else {
                $occ = (($totOCNight + $totOCRV) / ($tRoomT - ($totOO - $ooEndToday))) * 100;
            }
        } else {
            $tRoom = 0;
            $tRoom1 = 0;
            $_blocked = 0;
            $dt = $this->listRoomType();
            $inRoomType = [];
            $rmStock = 0;

            $reservCtl = new ReservationController();
            foreach ($dt as $roomType) {
                $roomTypeID = (int) $roomType->roomTypeID;
                $inRoomType[] = $roomTypeID;

                $totalRoom1 = 0;

                $rmStock += $reservCtl->getRoomStockByTypeWCheckout(
                    $roomTypeID,
                    \Carbon\Carbon::instance($dtArrival),
                    $tRoom,
                    '',
                    $totalRoom1,
                    false,
                    0,
                    '',
                    '',
                    false,
                    $_blocked,
                    true
                );
                $tRoom1 += $tRoom;
            }

            $strRoomType = implode(',', $inRoomType);

            $dtBlock = $this->countBlockedRoomTypeInventory($strRoomType, Carbon::parse($dtArrival)->toDateString(), Carbon::parse($dtArrival)->toDateString())->first();
            $dtOOO = $this->getOOOByDateRoomStockInventory(Carbon::parse($dtArrival)->toDateString(), Carbon::parse($dtArrival)->toDateString(), $strRoomType);
            $dtHU = $this->getHouseUseByDate(Carbon::parse($dtArrival)->toDateString(), Carbon::parse($dtArrival)->toDateString(), $strRoomType);

            // Filter OOO
            $dtOOOFiltered = collect($dtOOO)->filter(function ($row) use ($dtArrival) {
                return new \DateTime($row['roomOOOStart']) <= $dtArrival &&
                    new \DateTime($row['roomOOOEnd']) > $dtArrival;
            });

            // Filter HU
            $roomTypeTag = request()->input('roomTypeID'); // Simulasi ButtonEditRoomType.Tag
            $dtHUFiltered = collect($dtHU)->filter(function ($row) use ($dtArrival, $roomTypeTag) {
                return $row['stayDate'] === $dtArrival->format('Y-m-d') &&
                    (int) $row['roomTypeID'] === (int) $roomTypeTag;
            });

            $bl = 0;
            if (!empty($dtBlock) && is_numeric($dtBlock[0][0])) {
                $bl = (int) $dtBlock[0][0];
            }

            $socc = $rmStock + $tRoom1 + $bl + count($dtOOOFiltered);

            if ($includeHU) {
                $occ = (($tRoom1 - count($dtHUFiltered)) / ($socc - count($dtHUFiltered))) * 100;
            } else {
                $occ = ($tRoom1 / $socc) * 100;
            }
        }

        return round($occ, 2);
    }

    /**
     * Dapatkan jumlah Out Of Order (OO) dan daftar room yang digunakan
     *
     * @param Carbon|string $dtToCheck
     * @param bool $isMonth
     * @param bool $isYear
     * @param bool $isReport
     * @param bool $toMonthDate
     * @param string &$usedRoom  (kembali by-ref, akan berakhiran koma seperti original)
     * @param bool $isThread     (tidak digunakan; disimpan untuk kompatibilitas)
     * @return int
     */
    public static function getOOCount($dtToCheck, bool $isMonth = false, bool $isYear = false, bool $isReport = false, bool $toMonthDate = false, string &$usedRoom = "", bool $isThread = false): int
    {
        try {
            $date = $dtToCheck instanceof Carbon ? $dtToCheck : Carbon::parse($dtToCheck);

            if (! $isReport) {
                // query ke tblroomstocknew
                $qb = DB::table('tblroomstocknew')
                    ->select('roomNo')
                    ->where('roomNo', '<>', -5);

                if (! $isMonth && ! $isYear) {
                    $qb->whereRaw('DATE(stayDate) >= DATE(?)', [$date->toDateString()])
                        ->whereRaw('DATE(stayDate) <= DATE(?)', [$date->toDateString()]);
                } elseif ($isMonth) {
                    $qb->whereRaw('MONTH(stayDate) = ?', [$date->month])
                        ->whereRaw('YEAR(stayDate) = ?', [$date->year]);

                    if ($toMonthDate) {
                        $qb->whereRaw('DATE(stayDate) <= DATE(?)', [$date->toDateString()]);
                    }
                } elseif ($isYear) {
                    $qb->whereRaw('YEAR(stayDate) = ?', [$date->year]);

                    if ($toMonthDate) {
                        $qb->whereRaw('DATE(stayDate) <= DATE(?)', [$date->toDateString()]);
                    }
                }

                $qb->where('isOO', '<>', 0);

                $rows = $qb->get();

                foreach ($rows as $r) {
                    $room = (string) $r->roomNo;
                    if ($usedRoom !== "" && substr($usedRoom, -1) !== ",") {
                        $usedRoom .= ",";
                    }
                    $usedRoom .= $room . ",";
                }

                return $rows->count();
            } else {
                // report: ambil aggregate dari tblroomstatistic
                // Asumsi: OutOfOrder harus di-sum
                $qb = DB::table('tblroomstatistic')
                    ->select(DB::raw('COALESCE(SUM(OutOfOrder),0) AS totalOO'));

                if (! $isMonth && ! $isYear) {
                    $qb->whereRaw('DATE(statDate) = DATE(?)', [$date->toDateString()]);
                } elseif ($isMonth) {
                    $qb->whereRaw('MONTH(statDate) = ?', [$date->month])
                        ->whereRaw('YEAR(statDate) = ?', [$date->year]);

                    if ($toMonthDate) {
                        $qb->whereRaw('DATE(statDate) <= DATE(?)', [$date->toDateString()]);
                    }
                } elseif ($isYear) {
                    $qb->whereRaw('YEAR(statDate) = ?', [$date->year]);

                    if ($toMonthDate) {
                        $qb->whereRaw('DATE(statDate) <= DATE(?)', [$date->toDateString()]);
                    }
                }

                $row = $qb->first();

                if ($row && isset($row->totalOO)) {
                    return (int) $row->totalOO;
                }

                return 0;
            }
        } catch (\Exception $e) {
            // jika error, kembalikan 0 
            return 0;
        }
    }

    /**
     * Ambil daftar room types
     *
     * @param int $roomTypeID
     * @param string $notInSTR  (koma-separasi id, contoh: "1,2,3")
     * @param bool $orderByLevel
     * @param bool $orderByCode
     * @param string $roomTypeShortCode
     * @return Collection
     */
    public static function listRoomType(int $roomTypeID = 0, string $notInSTR = '', bool $orderByLevel = false, bool $orderByCode = false, string $roomTypeShortCode = '')
    {
        // Jika aplikasi Anda mendefinisikan BOOL_invSortByRoomTypeLevel sebagai konstanta, gunakan itu:
        $BOOL_invSortByRoomTypeLevel = DB::table('tblcomp')->first()->invSortByRoomTypeLevel;

        if ((bool)$BOOL_invSortByRoomTypeLevel) {
            $orderByLevel = true;
            $orderByCode = false;
        } else {
            // default behavior in original code sets orderByCode = true when BOOL_invSortByRoomTypeLevel = false
            if (! $orderByLevel && ! $orderByCode) {
                $orderByCode = true;
            }
        }

        $qb = DB::table('tblroomtype')
            ->select(
                'roomTypeID',
                'roomType',
                'roomTypeCode',
                'roomTypeShortCode',
                'maxAdult',
                'maxChild'
            )

            ->selectRaw("'' AS roomCount")
            ->where('isTypeActive', 1);

        if ($roomTypeID !== 0) {
            $qb->where('roomTypeID', $roomTypeID);
        }

        if (! empty($notInSTR)) {
            // sanitize and convert to array of ints
            $ids = array_filter(array_map('trim', explode(',', $notInSTR)), function ($v) {
                return $v !== '' && is_numeric($v);
            });
            if (! empty($ids)) {
                $qb->whereNotIn('roomTypeID', $ids);
            }
        }

        if (! empty($roomTypeShortCode)) {
            $qb->where('roomTypeShortCode', $roomTypeShortCode);
        }

        if ($orderByLevel) {
            $qb->orderBy('roomTypeLevel', 'asc');
        } elseif ($orderByCode) {
            $qb->orderBy('roomTypeCode', 'asc');
        } else {
            $qb->orderBy('roomTypeID', 'asc');
        }

        $rows = $qb->get();

        return $rows;
    }

    /**
     * Ambil jumlah Out Of Order hari ini dan bangun string roomNo (dipisah koma, berakhiran koma)
     *
     * @param bool $isThread  (tidak digunakan pada versi ini)
     * @param string &$roomNo (dikembalikan by-ref)
     * @return int
     */
    public static function getOOCountToday(string &$roomNo = ""): int
    {
        $defaultActiDate = Carbon::parse(session('activeDate'));

        $rows = DB::table('tblroom')
            ->select('roomNo')
            ->where('statusID', 4)
            ->whereRaw('DATE(statusEnd) = DATE(?)', [$defaultActiDate])
            ->get();

        if ($rows->isNotEmpty()) {
            foreach ($rows as $r) {
                $roomNo .= (string)$r->roomNo . ',';
            }
        }

        return $rows->count();
    }

    /**
     * Count occupied rooms for dashboard and build roomNo string (comma-terminated)
     *
     * @param Carbon|string $dtToCheck
     * @param string &$roomNo
     * @return int
     */
    public static function listDashOccRoom($dtToCheck, string &$roomNo = ""): int
    {
        $date = $dtToCheck instanceof Carbon ? $dtToCheck->toDateString() : Carbon::parse($dtToCheck)->toDateString();

        $rows = DB::table('tbltrans')
            ->select('NoTrans', 'roomNo')
            ->where('Stat', 5)
            ->where('roomNo', '<>', 0)
            ->whereRaw('DATE(DepartDate) > DATE(?)', [$date])
            ->get();

        foreach ($rows as $r) {
            $roomNo .= (string)$r->roomNo . ',';
        }

        return $rows->count();
    }

    /**
     * Dapatkan reservasi yang occupied (check-in) hari ini dan bangun string roomNo
     *
     * @param Carbon|string $dtToCheck
     * @param string &$roomNo  (dikembalikan by-ref, room dipisah koma dan berakhiran koma)
     * @return \Illuminate\Support\Collection
     */
    public static function getOccupiedRoomReservToday($dtToCheck, string &$roomNo = "")
    {
        $date = $dtToCheck instanceof Carbon ? $dtToCheck->toDateString() : Carbon::parse($dtToCheck)->toDateString();

        $rows = DB::table('tblreservation')
            ->select('NoReserv', 'roomNo')
            ->where('rIsMasterGroup', 0)
            ->where('isWaitingList', 0)
            ->whereRaw('DATE(CheckInDate) = DATE(?)', [$date])
            ->whereIn('Stat', [1, 5, 6])
            ->get();

        if ($rows->isNotEmpty()) {
            foreach ($rows as $r) {
                $roomNo .= (string)$r->roomNo . ',';
            }
        }

        return $rows;
    }

    public static function xbedStockInv()
    {
        $latest = DB::table('tblstockextrabed')
            ->select(['Stock', 'dateID'])
            ->where('isDeleted', 0)
            ->orderByDesc('dateID')
            ->limit(1)
            ->first();

        return !empty($latest) && isset($latest->Stock) ? (int) $latest->Stock : 0;
    }

    public static function countBlockedRoomType($roomTypeID, $dtTrans)
    {
        $date = Carbon::parse($dtTrans)->format('Y-m-d');

        $query = DB::table('tblblocking')
            ->join('tblblockingd', 'tblblockingd.NoTransBlock', '=', 'tblblocking.NoTransBlock')
            ->where('tblblocking.statBlock', 2)
            ->whereDate('tblblockingd.dDate', '=', $date);

        if ($roomTypeID != 0) {
            $query->where('tblblockingd.roomTypeID', $roomTypeID);
        }

        $total = $query->sum('tblblockingd.Block');

        return (int) $total;
    }

    public static function countMultiRoomType($roomTypeID, $onDate, $roomNo = 0, $excludeNoTrans = '')
    {
        $onDate = Carbon::parse($onDate)->format('Y-m-d');

        // Lookup roomTypeID if not provided
        if ($roomTypeID == 0) {
            if ($roomNo != 0) {
                $roomTypeID = DB::table('tblroom')
                    ->where('roomNo', $roomNo)
                    ->value('roomTypeID');
            } else {
                return 0;
            }
        }

        // Build query
        $query = DB::table('tblmultiroom')
            ->select([
                'NoTrans',
                'FromDt',
                'ToDt',
                'dtDepart',
                'oriRoomTypeID'
            ])
            ->where('roomTypeID', $roomTypeID)
            ->whereDate('dtDepart', '>=', $onDate);

        if (!empty($excludeNoTrans)) {
            $query->where('NoTrans', '<>', $excludeNoTrans);
        }

        if ($roomNo != 0) {
            $query->where('roomNo', $roomNo);
        }

        $rows = $query->get();

        $used = 0;

        foreach ($rows as $row) {
            if (!empty($row->dtDepart)) {
                $fd = Carbon::parse($row->FromDt)->format('Y-m-d');
                $td = Carbon::parse($row->ToDt)->format('Y-m-d');
                $depart = Carbon::parse($row->dtDepart)->format('Y-m-d');

                if ($fd === $onDate) {
                    $used += 1;
                } elseif ($fd <= $onDate && $td >= $onDate) {
                    if ($depart !== $onDate && $td !== $onDate) {
                        $used += 1;
                    }
                }

                if ((int) $row->oriRoomTypeID === (int) $roomTypeID) {
                    $used = 0;
                }
            }
        }

        return $used;
    }

    public static function countMultiRoomType2($roomTypeID, $onDate, $roomNo = 0, $excludeNoTrans = '')
    {
        $onDate = Carbon::parse($onDate)->format('Y-m-d');

        $query = DB::table('tblmultiroom')
            ->select([
                'NoTrans',
                'FromDt',
                'ToDt',
                'dtDepart',
                'roomTypeID'
            ])
            ->whereRaw('roomTypeID <> oriRoomTypeID')
            ->whereDate('dtDepart', '>=', $onDate);

        if ($roomTypeID != 0) {
            $query->where('oriRoomTypeID', $roomTypeID);
        }

        if (!empty($excludeNoTrans)) {
            $query->where('NoTrans', '<>', $excludeNoTrans);
        }

        if ($roomNo != 0) {
            $query->where('roomNo', $roomNo);
        }

        $rows = $query->get();
        $used = 0;

        foreach ($rows as $row) {
            if (!empty($row->dtDepart)) {
                $fd = Carbon::parse($row->FromDt)->format('Y-m-d');
                $td = Carbon::parse($row->ToDt)->format('Y-m-d');
                $depart = Carbon::parse($row->dtDepart)->format('Y-m-d');

                if ($fd === $onDate) {
                    $used -= 1;
                } elseif ($fd <= $onDate && $td >= $onDate) {
                    if ($depart !== $onDate && $td !== $onDate) {
                        $used -= 1;
                    }
                }
            }
        }

        return $used;
    }


    /**
     * List Reservation for Browser
     *
     * @param string $reservNo
     * @param bool $includeCancel
     * @param string $stat
     * @param string $noMice
     * @param bool $guestWithoutRoom
     * @param int $isMasterGroup
     * @param bool $incLegend
     * @param string $inReservation
     * @param string $inCompID
     * @param bool $pickupOnly
     * @param string $strSearch
     * @param int $dateType : 0=Created, 1=Arrival, 2=Departure
     * @param string|null $fromDt
     * @param string|null $toDt
     * @return \Illuminate\Support\Collection
     * Example for reservation browser: Reservation::listReservationBrowser("", true, "1", "", false, 0, true, "", "", true, SearchTerm, 0, '2025-01-01', '2025-01-31');
     */
    public static function listReservationBrowser(
        $reservNo = "",
        $includeCancel = true,
        $stat = "",
        $noMice = "",
        $guestWithoutRoom = false,
        $isMasterGroup = 0,
        $incLegend = false,
        $inReservation = "",
        $inCompID = "",
        $pickupOnly = false,
        $strSearch = "",
        $dateType = 0,
        $fromDt = null,
        $toDt = null
    ) {
        // Build the main query
        $query = DB::table('tblreservation as g')
            ->select(
                'g.Balance',
                'g.CheckInDate',
                'g.CheckOutDate',
                DB::raw("DATE_FORMAT(g.CheckInDate, '%Y%m%d') as inDateStr"),
                DB::raw("DATE_FORMAT(g.CheckOutDate, '%Y%m%d') as outDateStr"),
                'g.ClientID',
                'g.DateReserv',
                'g.Discount',
                'g.FolioNo',
                'g.NoReserv',
                'g.rateComp',
                'g.Remark1',
                'g.Remark2',
                'g.rEmail',
                DB::raw("IF(g.isWaitingList = 0, 'No', 'Yes') as waitStr"),
                'g.isWaitingList',
                'g.roomNo',
                'g.roomRate',
                'g.Child',
                'g.Infant',
                'g.Adult',
                DB::raw("CONCAT(g.Adult, '/', g.Child) as Pax"),
                'g.tariffID',
                'g.roomRate',
                'tbluser.Username as CreatedByName',
                'g.CreatedBy',
                'g.CreatedOn',
                'g.UpdatedBy',
                'g.UpdatedOn',
                'g.rateid',
                'g.roomTypeID',
                'tblroomtype.roomType',
                'tblroomtype.roomTypeCode',
                'g.Stat',
                DB::raw("IF(g.isNoShow <> 0, 'No Show', tblstatusreservation.StatName) as StatName"),
                'g.rAddress as Address',
                'g.rFirstName as FirstName',
                'g.rLastName as LastName',
                DB::raw("CONCAT_WS(' ', g.rFirstName, g.rLastName) as GuestName"),
                DB::raw("CONCAT_WS(' - ', g.FolioNo, CONCAT_WS(' ', g.rFirstName, g.rLastName)) as FolioGuest"),
                'g.rBusID as busID',
                'g.rCity as City',
                'g.rClientType as ClientType',
                'g.rCountryID as CountryID',
                'g.rEmail as Email',
                'g.rFax as Fax',
                'g.rPhone as Phone',
                'g.rMobile',
                'g.rVIP',
                'g.rCutOnDate',
                'g.channelCode',
                'g.isNoShowAuto',
                'g.SegmentIDSub',
                DB::raw("IFNULL((SELECT tariffComp 
                                FROM tbltariffcompany 
                                WHERE tariffID = g.tariffID 
                                AND CompID = g.rCompID 
                                AND DATE(startDate) <= DATE(g.CheckInDate) 
                                AND DATE(endDate) >= DATE(g.CheckInDate)), 0) as CompRate"),
                DB::raw("CONCAT_WS(' ', g.rFirstName, g.rLastName) as ClientName"),
                'g.rFirstName',
                'g.rLastName',
                'g.rCompName as CompName',
                'g.rCompID as CompID',
                'g.segmentID',
                'g.CityID',
                'g.DestCityID',
                'g.rBusID',
                DB::raw("IF(tblbusinesssource.busID IS NULL, '', CONCAT_WS(' - ', tblbusinesssource.busName, tblbusinesssource.busRemark)) as BusSource"),
                'g.rCompID',
                'tblsegment.segmentName',
                DB::raw("CONCAT(tblrate.RateName, ' ', CONCAT('(', ' ', CONCAT_WS(' ', tblseason.seasonName, tblsegment.segmentName), ')')) as RateName"),
                'tblrate.RateCode',
                'tbltariff.tariffID',
                'g.rCurrencyID',
                'g.rAlreadyUpdated',
                'g.NoMice',
                'g.RemarkPOS',
                'g.isNoShow',
                'g.BookingID',
                'g.rIsMasterGroup',
                'tbltransreserv.bed',
                'tbltransreserv.xtraBed',
                'g.rGuestTitle',
                'g.rMemberID',
                'g.rCompName',
                'g.rAddress',
                'g.rIDNumber',
                'g.rIDType',
                'g.rCountryID',
                'g.rPhone',
                'g.rOldRoomTypeID',
                'g.rIsPickup',
                DB::raw("'' as GroupFolio"),
                DB::raw("IF(g.roomno = 0, '-', IF(tblroom.statusID = 4, 'OOI', IF(tblroom.isRoomOOO <> 0, 'OOS', IF(tblroom.isHold <> 0, 'HOLD', tblroomstatus.ShortName)))) as ShortName"),
                DB::raw("IF(g.roomno = 0, '-', IF(isRoomOOO <> 0, 'OOS', IF(isHold <> 0, IF(tblroom.statusID = 5, CONCAT('VD', '/', 'HOLD'), IF(tblroom.statusID = 6, CONCAT('VCR', '/', 'HOLD'), 'HOLD')), IF(tblroomstatus.statusName <> 'Out of Inventory', CONCAT(SUBSTRING(TRIM(SUBSTRING_INDEX(tblroomstatus.statusName, ' ', 1)), 1, 1), '', SUBSTRING(TRIM(SUBSTRING_INDEX(tblroomstatus.statusName, ' ', -1)), 1, 1)), 'OOI')))) as RoomStatus"),
                'tblroom.isHold',
                'tblroom.isRoomOOO',
                'tblroom.isInspect',
                'tblroom.statusID',
                'tblroom.roomAlias',
                'g.rHourlyRateID',
                'g.rHourlyStart',
                'g.rHourlyEnd'
            );

        // Add legend fields if needed
        if ($incLegend) {
            $query->addSelect([
                DB::raw("IFNULL(tbltranslegend.isHasDepositPaid, 0) as isHasDepositPaid"),
                DB::raw("IFNULL(tbltranslegend.isHasDepositNotPaid, 0) as isHasDepositNotPaid"),
                DB::raw("IFNULL(tbltranslegend.isHasDepositBox, 0) as isHasDepositBox"),
                DB::raw("IFNULL(tbltranslegend.isHasDepositReq, 0) as isHasDepositReq"),
                DB::raw("IFNULL(tbltranslegend.isHasLockedRoom, 0) as isHasLockedRoom"),
                DB::raw("IFNULL(tbltranslegend.isHasHoneyMoon, 0) as isHasHoneyMoon"),
                DB::raw("IFNULL(tbltranslegend.isHasCashOnlyNOCF, 0) as isHasCashOnlyNOCF"),
                DB::raw("IFNULL(tbltranslegend.isHasHouseUse, 0) as isHasHouseUse"),
                DB::raw("IFNULL(tbltranslegend.isHasRepeatGuest, 0) as isHasRepeatGuest"),
                DB::raw("IFNULL(tbltranslegend.isHasXBed, 0) as isHasXBed"),
                DB::raw("IFNULL(tbltranslegend.isHasLongStay, 0) as isHasLongStay"),
                DB::raw("IFNULL(tbltranslegend.isHasForeigner, 0) as isHasForeigner"),
                DB::raw("IFNULL(tbltranslegend.isHasVIP1, 0) as isHasVIP1"),
                DB::raw("IFNULL(tbltranslegend.isHasVIP2, 0) as isHasVIP2"),
                DB::raw("IFNULL(tbltranslegend.isHasVIP3, 0) as isHasVIP3"),
                DB::raw("IFNULL(tbltranslegend.isHasVVIP, 0) as isHasVVIP"),
                DB::raw("IFNULL(tbltranslegend.isHasWalkIn, 0) as isHasWalkIn"),
                DB::raw("IFNULL(tbltranslegend.isHasCashBasis, 0) as isHasCashBasis"),
                DB::raw("IFNULL(tbltranslegend.isHasInternetReservation, 0) as isHasInternetReservation"),
                DB::raw("IFNULL(tbltranslegend.isHasEarlyCheckin, 0) as isHasEarlyCheckin"),
                DB::raw("IFNULL(tbltranslegend.isHasUpgradedRoomType, 0) as isHasUpgradedRoomType"),
                DB::raw("IFNULL(tbltranslegend.isHasRoomMove, 0) as isHasRoomMove"),
                DB::raw("IFNULL(tbltranslegend.isHasNoPayTV, 0) as isHasNoPayTV")
            ]);
        }

        // Add joins
        $query->join('tblroomtype', 'tblroomtype.roomTypeID', '=', 'g.roomTypeID')
            ->join('tblstatusreservation', 'tblstatusreservation.StatID', '=', 'g.Stat')
            ->join('tblrate', 'tblrate.RateID', '=', 'g.rateid')
            ->join('tblsegment', 'tblsegment.segmentID', '=', 'g.segmentID')
            ->join('tbltariff', 'tbltariff.tariffID', '=', 'g.tariffID')
            ->join('tblseason', 'tblseason.seasonID', '=', 'tbltariff.seasonID')
            ->join('tbltransreserv', 'tbltransreserv.NoTrans', '=', 'g.NoReserv')
            ->join('tblroom', 'tblroom.roomNo', '=', 'g.roomNo')
            ->join('tblroomstatus', 'tblroomstatus.StatusID', '=', 'tblroom.statusID')
            ->leftJoin('tbluser', 'tbluser.userID', '=', 'g.CreatedBy')
            ->leftJoin('tblbusinesssource', 'tblbusinesssource.busID', '=', 'g.rBusID');

        if ($incLegend) {
            $query->leftJoin('tbltranslegend', 'tbltranslegend.FolioNo', '=', 'g.FolioNo');
        }

        // Add where conditions
        $query->where('g.Stat', '<>', 0)
            ->where('g.rIsMasterGroup', '=', $isMasterGroup);

        if (!$includeCancel) {
            // dd($includeCancel);
            $query->where('g.Stat', '<>', 4);
        }

        if (!empty($stat)) {
            $query->whereRaw("g.Stat IN ($stat)");
        }

        if ($pickupOnly) {
            $query->where('g.rIsPickup', '<>', 0);
        }

        if (!empty($reservNo)) {
            $query->where('g.NoReserv', '=', $reservNo);
        }

        if (!empty($inReservation)) {
            $query->whereRaw("g.NoReserv IN ($inReservation)");
        }

        if (!empty($noMice)) {
            $query->where('g.NoMice', '=', $noMice);
        }

        if ($guestWithoutRoom) {
            $query->where('g.roomNo', '=', 0);
        }

        if (!empty($inCompID)) {
            $query->whereRaw("g.rCompID IN ($inCompID)");
        }

        // Date filtering
        if ($fromDt && $toDt) {
            $fromDate = Carbon::parse($fromDt)->startOfDay();
            $toDate = Carbon::parse($toDt)->endOfDay();

            if ($dateType == 0) {
                $query->whereDate('g.CreatedOn', '>=', $fromDate)
                    ->whereDate('g.CreatedOn', '<=', $toDate);
            } elseif ($dateType == 1) {
                $query->whereDate('g.CheckInDate', '>=', $fromDate)
                    ->whereDate('g.CheckInDate', '<=', $toDate);
            } elseif ($dateType == 2) {
                $query->whereDate('g.CheckOutDate', '>=', $fromDate)
                    ->whereDate('g.CheckOutDate', '<=', $toDate);
            }
        }

        // Search condition
        if (!empty($strSearch)) {
            $searchTerm = "%$strSearch%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('g.NoReserv', 'LIKE', $searchTerm)
                    ->orWhere('g.NoMice', 'LIKE', $searchTerm)
                    ->orWhere('g.roomNo', 'LIKE', $searchTerm)
                    ->orWhere('tblroomtype.roomType', 'LIKE', $searchTerm)
                    ->orWhere('g.rFirstName', 'LIKE', $searchTerm)
                    ->orWhere('g.rLastName', 'LIKE', $searchTerm)
                    ->orWhere('g.Remark1', 'LIKE', $searchTerm)
                    ->orWhere('tblbusinesssource.busName', 'LIKE', $searchTerm)
                    ->orWhere('tblbusinesssource.busRemark', 'LIKE', $searchTerm)
                    ->orWhere('g.rCompName', 'LIKE', $searchTerm)
                    ->orWhere('g.Remark2', 'LIKE', $searchTerm)
                    ->orWhere('g.FolioNo', 'LIKE', $searchTerm)
                    ->orWhereRaw("IF(g.isWaitingList = 0, 'No Waiting', 'Yes Waiting') LIKE ?", [$searchTerm])
                    ->orWhereRaw("IF(g.isNoShow <> 0, 'No Show', tblstatusreservation.StatName) LIKE ?", [$searchTerm])
                    ->orWhere('g.BookingID', 'LIKE', $searchTerm);
            });
        }

        // Order by
        $query->orderBy('g.CheckInDate', 'ASC');

        // Execute query
        $results = $query->get();

        // dd($results);

        // Process NoMice grouping logic
        if ($results->isNotEmpty()) {
            $noMiceList = [];
            foreach ($results as $result) {
                if (!empty($result->NoMice) && !in_array("'" . $result->NoMice . "'", $noMiceList)) {
                    $noMiceList[] = "'" . $result->NoMice . "'";
                }
            }

            if (!empty($noMiceList)) {
                $noMiceString = implode(',', $noMiceList);

                $miceQuery = DB::table('tblreservation')
                    ->select('FolioNo', 'NoMice')
                    ->where('rIsMasterGroup', '<>', 0)
                    ->whereRaw("NoMice IN ($noMiceString)")
                    ->get();

                $miceData = $miceQuery->groupBy('NoMice');

                // Update GroupFolio for each result
                foreach ($results as $result) {
                    if (!empty($result->NoMice) && isset($miceData[$result->NoMice])) {
                        $result->GroupFolio = $miceData[$result->NoMice][0]->FolioNo ?? '';
                    }
                }
            }

            // Update client data if needed
            $firstResult = $results->first();
            if ($firstResult->rAlreadyUpdated == 0) {
                $clientData = DB::table('tblclient')
                    ->select(
                        'Fax',
                        'CountryID',
                        'TypeIDRelDate',
                        'TypeIDNumber',
                        'TypeIDExpDate',
                        'TypeID',
                        'Email',
                        'Phone',
                        'Postal',
                        'City',
                        'ClientType',
                        'Address'
                    )
                    ->where('clientID', $firstResult->ClientID)
                    ->first();

                if ($clientData) {
                    DB::table('tblreservation')
                        ->where('NoReserv', $firstResult->NoReserv)
                        ->update([
                            'rFax' => $clientData->Fax,
                            'rIDExp' => $clientData->TypeIDExpDate,
                            'rIDNumber' => $clientData->TypeIDNumber,
                            'rIDRel' => $clientData->TypeIDRelDate,
                            'rIDType' => $clientData->TypeID,
                            'rEmail' => $clientData->Email,
                            'rPhone' => $clientData->Phone,
                            'rPostal' => $clientData->Postal,
                            'rCity' => $clientData->City,
                            'rClientType' => $clientData->ClientType,
                            'rCountryID' => $clientData->CountryID,
                            'rAddress' => $clientData->Address,
                            'rAlreadyUpdated' => 1
                        ]);
                }
            }
        }

        return $results;
    }


    public function getReservationByLastCheckOut(
        int $roomNo,
        bool $isForToday = false
    ): ?array {
        $defaultActiDate = Carbon::parse(session('activeDate'));

        // First query: tblReservationx
        $reservation = DB::table('tblreservation')
            ->select([
                'CheckInDate',
                'CheckOutDate',
                'Remark2',
                'FolioNo',
                'NoReserv',
                DB::raw("CONCAT_WS(' ', rFirstName, rLastName) AS GuestName")
            ])
            ->where('roomNo', $roomNo)
            ->whereNotIn('Stat', [0, 4, 7, 8]);

        if ($isForToday) {
            $reservation->whereDate('CheckInDate', $defaultActiDate);
        }

        $result = $reservation->limit(1)->first();

        if ($result) {
            return [
                'GuestName' => $result->GuestName,
                'inDate' => Carbon::parse($result->CheckInDate),
                'outDate' => Carbon::parse($result->CheckOutDate),
                'NoReserv' => $result->NoReserv,
                'remark' => $result->Remark2 ?? '',
                'FolioNo' => $result->FolioNo ?? '',
            ];
        }

        // Fallback query: tblTransMicex + joins
        $fallback = DB::table('tbltransmice')
            ->join('tbltransmicemember', 'tbltransmice.NoMice', '=', 'tbltransmicemember.NoMice')
            ->join('tblclient', 'tbltransmice.clientID', '=', 'tblclient.clientID')
            ->select([
                'tbltransmicemember.arrDate AS CheckInDate',
                'tbltransmicemember.depDate AS CheckOutDate',
                DB::raw("CONCAT_WS(' ', tblclient.FirstName, tblclient.LastName) AS GuestName"),
                DB::raw("'' AS NoReserv"),
                DB::raw("'' AS Remark2"),
                DB::raw("'' AS FolioNo")
            ])
            ->where('tbltransmicemember.roomNo', $roomNo)
            ->where('tbltransmice.Stat', '<>', 4)
            ->where('tbltransmice.Stat', '<', 7);

        if ($isForToday) {
            $fallback->whereDate('tbltransmicemember.arrDate', $defaultActiDate);
        }

        $result2 = $fallback->limit(1)->first();

        if ($result2) {
            return [
                'GuestName' => $result2->GuestName,
                'inDate' => Carbon::parse($result2->CheckInDate),
                'outDate' => Carbon::parse($result2->CheckOutDate),
                'NoReserv' => $result2->NoReserv,
                'remark' => $result2->Remark2 ?? '',
                'FolioNo' => $result2->FolioNo ?? '',
            ];
        }

        return null;
    }

    /// Validasi Validasi Reservation sebelum Check-In
    /// Mengembalikan null jika validasi lolos, atau string kode error jika gagal
    public function validateCheckIn(string $noReserv, string $FolioNo, \Carbon\Carbon $dtArrival, \Carbon\Carbon $dtDepature, int $roomNo): ?string
    {
        $defaultActiDate = Carbon::parse(session('activeDate'));

        // 🔍 Cek status reservasi
        $reservationStatus = DB::table('tblreservation')
            ->where('NoReserv', $noReserv)
            ->value('Stat');

        if ((int)$reservationStatus === 1) { // TENTATIVE
            $tblsetting = DB::table('tblsetting')->where('setActive', 1)->where('setName', 'allow_ci_tentative')->first();
            if (isset($tblsetting) && $tblsetting->setValue == 1) {
            } else {
                session()->flash('checkin_error', __('TENTATIVE reservation is not allowed to Check-In. Please change the status to Confirmed/Guaranteed.'));
                return 'tentative_blocked';
            }
        } else if ((int)$reservationStatus > 6) { // ALREADY CHECKED IN
            session()->flash('checkin_error', __('This reservation has already been checked in.'));
            return 'already_checked_in';
        } else if (in_array((int)$reservationStatus, [0, 4, 7, 8])) { // INVALID STATUS
            session()->flash('checkin_error', __('This reservation is not valid for Check-In.'));
            return 'invalid_status';
        }

        // 📅 Validasi tanggal kedatangan
        if ($dtArrival->toDateString() !== $defaultActiDate) {
            session()->flash('checkin_error', __('Invalid arrival date. It should be set to today\'s active date.'));
            return 'invalid_arrival_date';
        }

        // 🏨 Validasi nomor kamar
        if ($roomNo === 0) {
            session()->flash('checkin_error', __('Invalid room specified.'));
            return 'invalid_room';
        }

        if (self::isRoomHold($roomNo)) {
            session()->flash('checkin_error', __('The selected room is currently on hold. Please choose another room.'));
            return 'room_on_hold';
        }

        $isinspect = DB::table('tblroom')->where('roomNo', $roomNo)->first();
        if ($isinspect && $isinspect->isInspect !== 0) {
            session()->flash('checkin_error', __('The selected room is currently under inspection. Please choose another room.'));
            return 'room_under_inspection';
        }

        if (self::isRoomOOO($roomNo)) {
            session()->flash('checkin_error', __('The selected room is marked as Out of Order (OOO). Please choose another room.'));
            return 'room_ooo';
        }

        if (self::listWaitingByRoom($roomNo, $noReserv, $dtArrival, $dtDepature) != '') {
            session()->flash('checkin_error', __('The selected room has pending reservations (waiting list). Please choose another room.'));
            return 'room_has_pending_reservations';
        }

        $dpr = DB::table('tbltranslegend')->where('FolioNo', $FolioNo)->first();
        if ($dpr && $dpr->isHasDepositReq !== 0) {
            session()->flash('checkin_error', __('The selected room has not met the deposit requirements. Please complete the deposit before checking in.'));
            return 'room_deposit_required';
        }

        return null; // Semua validasi lolos
    }

    public function isRoomHold(int $roomNo): bool
    {
        $isHold = DB::table('tblRoomx')
            ->where('roomNo', $roomNo)
            ->value('isHold');

        return (bool) $isHold;
    }

    public function isRoomOOO(int $roomNo): bool
    {
        $isRoomOOO = DB::table('tblRoomx')
            ->where('roomNo', $roomNo)
            ->value('isRoomOOO');

        return (bool) $isRoomOOO;
    }

    public function listWaitingByRoom(int $roomNo, string $notInReserv, \Carbon\Carbon $dtIn, \Carbon\Carbon $outDate): string
    {
        $waitingList = DB::table('tblreservation')
            ->select([
                DB::raw("CONCAT_WS(' ', rFirstName, rLastName) AS GuestName"),
                'CheckInDate',
                'CheckOutDate'
            ])
            ->where('roomNo', $roomNo)
            ->where('isWaitingList', '<>', 0)
            ->where('NoReserv', '<>', $notInReserv)
            ->whereDate('CheckInDate', '>=', $dtIn->toDateString())
            ->whereDate('CheckInDate', '<=', $outDate->toDateString())
            ->whereNotIn('Stat', [0, 4])
            ->get();

        $result = '';

        foreach ($waitingList as $row) {
            $ci = \Carbon\Carbon::parse($row->CheckInDate)->format('d-M-Y');
            $co = \Carbon\Carbon::parse($row->CheckOutDate)->format('d-M-Y');
            $result .= "{$row->GuestName} (CI : {$ci} - CO : {$co})\n";
        }

        return $result;
    }

    /**
     * Get room status.
     *
     * @param int $roomNo
     * @param string|null $roomStatus (passed by reference)
     * @return int
     */
    public static function getRoomStat(int $roomNo, ?string &$roomStatus = ''): int
    {
        // Query mirrors the SQLSelectBuilder + join in the VB code
        $rows = DB::table('tblroom as r')
            ->select([
                'r.StatusID',
                'rs.StatusName',
                'r.roomTypeID',
                'r.isRoomOOO',
                'r.StatusID as StatusID',
                'r.isHold'
            ])
            ->join('tblroomstatus as rs', 'rs.StatusID', '=', 'r.StatusID')
            ->where('r.roomNo', $roomNo)
            ->limit(1)
            ->get();
        if ($rows->isNotEmpty()) {
            $row = $rows->first();

            $sReserv = new ReservationController();
            if ($sReserv->isRoomTypeActive((int) $row->roomTypeID) == false) {
                return RoomStat::OutOfInventory->value;
            }

            $roomStatus = (string) $row->StatusName;

            if ((int) $row->isRoomOOO === 1) {
                $roomStatus = 'Out of Service';
            } elseif ((int) $row->StatusID === 4) {
                $roomStatus = 'Out of Inventory';
            } elseif ((bool) $row->isHold === true) {
                $roomStatus = 'HOLD';
            }

            return (int) $row->StatusID;
        }

        return RoomStat::Normal->value;
    }

    /**
     * Save or update folio transaction.
     *
     * Assumptions made:
     * - There is a DB table name stored in $table = 'folios' (change to your actual table).
     * - Helper functions from original code are provided elsewhere:
     *     - autoGenerateCode(string $table, string $transType, ...): string
     *     - dLookUpDatareaderNew(string $field, string $table, string $where): ?string
     *     - dlookUpThread(string $field, string $table, string $where): ?string
     *     - getFolioBalanceByTrans(string $transType, string $noTrans, string $folioNo, bool $isThread): float
     * - transType constants are available or passed as strings ('RoomCharge','Adjustment','ExtraCharge','Payment').
     *
     * Signature mirrors VB.NET defaults; $msg passed by reference to mimic Optional ByRef msg As String.
     */
    public static function saveFolioTrans(
        string $noTrans,
        float $totRate,
        float $totCharges,
        float $totPayment,
        string $FolioNo = '',
        string &$msg = '',
        string $masterFolio = '',
        string $remark = '',
        string $folioType = '',
        float $CreditLimit = -1,
        bool $showErrMsg = true
    ): bool {
        $table = 'tblfolio'; // replace with your actual table name

        try {
            // Ensure FolioNo exists
            if (empty($FolioNo)) {
                // autoGenerateCode should be implemented elsewhere
                $cgen = new CodeGeneratorService();
                $FolioNo = $cgen->autoGenerateCode($table, 'RoomCharge');
            }

            // Check if folio exists
            $whereClause = "NoTrans = ? AND FolioNo = ?";
            $bindings = [$noTrans, $FolioNo];

            $tfol = DB::table($table)->where('NoTrans', $noTrans)->where('FolioNo', $FolioNo)->first();
            $isFolioAvail = $tfol->FolioNo ?? null;

            // Recalculate balances using helper (must exist)
            $totAdjustment = self::getFolioBalanceByTrans(TransType::Adjustment, $noTrans, $FolioNo);
            $totCharges   = self::getFolioBalanceByTrans(TransType::ExtraCharge, $noTrans, $FolioNo);
            $totPayment   = self::getFolioBalanceByTrans(TransType::Payment, $noTrans, $FolioNo);

            $balance = ($totRate + $totCharges + $totAdjustment) - $totPayment;

            // Use transaction to be safe
            DB::beginTransaction();

            if (empty($isFolioAvail)) {
                // Insert
                $insert = [
                    'totRate'        => $totRate,
                    'totCharges'     => $totCharges,
                    'FolioNo'        => $FolioNo,
                    'NoTrans'        => $noTrans,
                    'totPayment'     => $totPayment,
                    'totAdjustment'  => $totAdjustment,
                    'balance'        => $balance,
                ];

                if ($remark !== '') {
                    $insert['Remark'] = $remark;
                }
                if ($folioType !== '') {
                    $insert['FolioType'] = $folioType;
                }
                if ($masterFolio !== '') {
                    $insert['masterFolio'] = $masterFolio;
                }
                if ($CreditLimit >= 0) {
                    $insert['CreditLimitFolio'] = $CreditLimit;
                }

                // INSERT IGNORE equivalent: use raw query with "insert ignore" or handle duplicate logic
                $columns = implode(',', array_map(fn($c) => "`$c`", array_keys($insert)));
                $placeholders = implode(',', array_fill(0, count($insert), '?'));
                $sql = "INSERT IGNORE INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
                DB::statement($sql, array_values($insert));
            } else {
                // Update
                $updateData = [
                    'totRate'       => $totRate,
                    'totCharges'    => $totCharges,
                    'FolioNo'       => $FolioNo,
                    'NoTrans'       => $noTrans,
                    'totPayment'    => $totPayment,
                    'totAdjustment' => $totAdjustment,
                    'balance'       => $balance,
                ];

                if ($remark !== '') {
                    $updateData['Remark'] = $remark;
                }
                if ($masterFolio !== '') {
                    $updateData['masterFolio'] = $masterFolio;
                }
                if ($folioType !== '') {
                    $updateData['FolioType'] = $folioType;
                }
                if ($CreditLimit >= 0) {
                    $updateData['CreditLimitFolio'] = $CreditLimit;
                }

                // Build dynamic update
                $setParts = [];
                $bindings = [];
                foreach ($updateData as $col => $val) {
                    $setParts[] = "`{$col}` = ?";
                    $bindings[] = $val;
                }
                $bindings[] = $FolioNo;
                $bindings[] = $noTrans;
                $setSql = implode(', ', $setParts);
                $sql = "UPDATE `{$table}` SET {$setSql} WHERE FolioNo = ? AND NoTrans = ?";
                DB::statement($sql, $bindings);
            }

            DB::commit();
            return true;
        } catch (\Throwable $ex) {
            DB::rollBack();
            $msg = $ex->getMessage();
            if ($showErrMsg) {
                Log::error($ex);
                // If you want to log: Log::error($ex);
            }
            return false;
        }
    }

    /**
     * Get folio balance sum for a specific transaction type, noTrans and FolioNo.
     *
     * @param int|string $transaksi  Transaction type id or enum value
     * @param string $noTrans
     * @param string $FolioNo
     * @return float
     */
    public static function getFolioBalanceByTrans(TransType $transaksi, string $noTrans, string $FolioNo): float
    {
        // Table and column names — replace with your actual names or use constants
        $table = 'tbltransd';
        $colTotal = 'total';
        $colNoTrans = 'NoTrans';
        $colFolioNo = 'FolioNo';
        $colTransaksiID = 'TransaksiID';
        $colStat = 'stat';

        // Ensure transaksi is integer (mirrors CInt(transaksi) in VB.NET)
        $transaksiId = $transaksi;

        // Use query builder to calculate SUM(total)
        $result = DB::table($table)
            ->selectRaw("SUM({$colTotal}) as Total")
            ->where($colNoTrans, '=', $noTrans)
            ->where($colFolioNo, '=', $FolioNo)
            ->where($colTransaksiID, '=', $transaksiId)
            ->where($colStat, '>', 4)
            ->first();

        // If no rows or Total is null, return 0.0
        $sum = $result && isset($result->Total) ? (float) $result->Total : 0.0;

        return $sum;
    }

    /**
     * Get master folio number.
     *
     * @param string $notrans
     * @param string &$folioType
     * @return string|int  folio number as string or 0 if not found
     */
    public static function getMasterFolioNo(string $notrans = '', string &$folioType = '')
    {
        $table = 'tblfolio';

        $query = DB::table($table)
            ->select('FolioNo', 'FolioType')
            ->where(function ($q) {
                $q->whereNull('masterFolio')
                    ->orWhere('masterFolio', '');
            });

        if (!empty($notrans)) {
            $query->where('NoTrans', $notrans);
        }

        $results = $query->get();

        if ($results->isNotEmpty()) {
            $first = $results->first();
            $folioType = (string) ($first->FolioType ?? '');
            return (string) ($first->FolioNo ?? '0');
        }

        return 0;
    }

    /**
     * Get folio master number by NoTrans.
     *
     * @param string $noTrans
     * @return string  Returns mFolioNo or empty string when not found
     */
    public static function getFolioMaster(string $noTrans): string
    {
        $table = 'tblfoliomaster';

        $row = DB::table($table)
            ->select('mFolioNo')
            ->where('mNoTrans', $noTrans)
            ->where('isSharer', 0)
            ->where('isAdditional', 0)
            ->first();

        if ($row && isset($row->mFolioNo)) {
            return (string) $row->mFolioNo;
        }

        return '';
    }

    /**
     * Get exchange rate offer and compute converted amount.
     *
     * @param string $ccyID
     * @param \DateTime|string $transDate
     * @param float $amount
     * @param float &$exchange    (by reference) output: exchange rate used (default 1)
     * @param int   &$rhitung     (by reference) output: hitung mode used (0 or 1)
     * @param bool  $isReverse
     * @param bool  $useMyRate
     * @param int   $hitung       optional input override for hitung (0 or 1)
     * @return float             converted amount (ttl)
     */
    public static function getExchangeRateOffer(
        string $ccyID,
        $transDate,
        float $amount,
        float &$exchange = 1.0,
        int &$rhitung = 0,
        bool $isReverse = false,
        bool $useMyRate = false,
        int $hitung = 0
    ): float {
        // Normalize date to Y-m-d for DATE(...) comparison
        $date = $transDate instanceof \DateTime ? $transDate->format('Y-m-d') : Carbon::parse($transDate)->format('Y-m-d');

        // Query: select OfferRate where DATE(DateID) <= transDate and CurrencyID = ccyID order by DateID desc limit 1
        $row = DB::table('tblexchangerate')
            ->select('OfferRate')
            ->whereDate('DateID', '<=', $date)
            ->where('CurrencyID', $ccyID)
            ->orderBy('DateID', 'desc')
            ->limit(1)
            ->first();

        if ($row) {
            // If caller didn't provide hitung override, fetch from currency table
            if ($hitung === 0) {
                $dbHitung = DB::table('tblcurrency')
                    ->where('CurrencyID', $ccyID)
                    ->value('Hitung');

                $hitung = is_null($dbHitung) ? 0 : (int)$dbHitung;
            }

            $rhitung = $hitung;

            // Determine exchange rate to use
            $exc = (float)$row->OfferRate;
            if ($useMyRate) {
                $exc = (float)$exchange;
            }

            $ttl = 0.0;

            try {
                if (! $isReverse) {
                    if ($hitung === 0) { // Divide
                        $ttl = $exc == 0.0 ? 0.0 : ($amount / $exc);
                    } else { // Multiply
                        $ttl = $amount * $exc;
                    }
                } else {
                    if ($hitung === 0) { // Multiply
                        $ttl = $amount * $exc;
                    } else { // Divide
                        $ttl = $exc == 0.0 ? 0.0 : ($amount / $exc);
                    }
                }
            } catch (\Throwable $e) {
                $ttl = 0.0;
            }

            $exchange = $exc;
            return $ttl;
        }

        // No rate found: exchange = 1 and return original amount
        $exchange = 1.0;
        return (float)$amount;
    }

    /**
     * Check if credit is limited.
     *
     * @param string $noTrans
     * @param string $folioNo
     * @param \DateTime|string $transDate
     * @param float $exchange
     * @param float $total
     * @param string $CurrencyID
     * @param int $transaksiID
     * @param string &$errMsg
     * @return bool  true when credit limit would be exceeded
     */
    public static function isCreditLimited(
        string $noTrans,
        string $folioNo,
        $transDate,
        float $exchange,
        float $total,
        string $CurrencyID,
        int $transaksiID,
        string &$errMsg = ''
    ): bool {
        // Assumptions: baseCurrency and transType::Payment must exist in your app.
        // Replace these with your actual constants or config values.
        $baseCurrency = session('baseCurrency');
        $paymentTransType = TransType::Payment->value ? TransType::Payment : 99;

        // Helper: format money similar to formatUang
        $formatUang = function ($v) {
            return number_format((float)$v, 2, '.', ',');
        };

        // Fetch credit limit depending on reservation or normal transaction
        if (str_starts_with($noTrans, 'R')) {
            $limitAmt = DB::table('tblreservation')
                ->where('NoReserv', $noTrans)
                ->value('rCreditLimit');
        } else {
            $limitAmt = DB::table('tbltrans')
                ->where('NoTrans', $noTrans)
                ->value('tCreditLimit');
        }

        $limitAmt = is_null($limitAmt) ? 0.0 : (float)$limitAmt;

        if ($limitAmt != 0.0) {
            // Normalize date
            $date = $transDate instanceof \DateTime ? $transDate->format('Y-m-d') : Carbon::parse($transDate)->format('Y-m-d');

            // Compute amount in base currency if needed
            $totAmt = 0.0;
            if ($CurrencyID !== $baseCurrency) {
                if ((int)$transaksiID !== (int)$paymentTransType) {
                    // use getExchangeRateOffer(ccyID, transDate, amount, exchange-byref, rhitung-byref, isReverse=false, useMyRate=false)
                    $tmpExchange = 1.0;
                    $tmpRhitung = 0;
                    $ttl = self::getExchangeRateOffer($CurrencyID, $date, $total, $tmpExchange, $tmpRhitung, false, false, 0);
                } else {
                    // for payments: pass provided $exchange and useMyRate = true
                    $tmpExchange = $exchange;
                    $tmpRhitung = 0;
                    $ttl = self::getExchangeRateOffer($CurrencyID, $date, $total, $tmpExchange, $tmpRhitung, false, true, 0);
                }
                $totAmt = (float)$ttl;
            } else {
                $totAmt = (float)$total;
            }

            // Get folio balance depending on reservation or normal folio
            if (str_starts_with($noTrans, 'R')) {
                $folBalance = DB::table('tbltransdreserv')
                    ->where('NoTrans', $noTrans)
                    ->where('stat', '>', 5)
                    ->sum('oriamount');
                $folBalance = is_null($folBalance) ? 0.0 : (float)$folBalance;
            } else {
                // Replace DALhotel::getFolioBalance with your implementation
                $folBalance = self::getFolioBalance($folioNo, $noTrans);
                $folBalance = is_null($folBalance) ? 0.0 : (float)$folBalance;
            }

            if (($folBalance + $totAmt) > $limitAmt) {
                $errMsg = "Can not continue. Credit Limit is not enough.\n"
                    . "Credit limit is : " . $formatUang($limitAmt) . ". Current Balance is :" . $formatUang($folBalance);
                return true;
            }
        }

        return false;
    }

    /**
     * Get folio balance by folioNo or NoTrans.
     *
     * @param string $folioNo
     * @param string $noTrans
     * @return float
     */
    public static function getFolioBalance(string $folioNo, string $noTrans = ''): float
    {
        if (trim($folioNo) === '' && trim($noTrans) === '') {
            return 0.0;
        }

        $query = DB::table('tbltransd')
            ->where('stat', '>=', 6);

        if (trim($noTrans) === '') {
            $query->where('FolioNo', $folioNo);
        } else {
            $query->where('NoTrans', $noTrans);
        }

        $sum = $query->sum('amount');

        return is_numeric($sum) ? (float)$sum : 0.0;
    }

    /**
     * List folio IN directions by master folio.
     *
     * @param string $MasterFolioNo
     * @param \DateTime|string &$fromDt
     * @param \DateTime|string &$toDt
     * @param float &$dailyAmount
     * @param int &$dailyTotal
     * @param string &$INChargeType
     * @return \Illuminate\Support\Collection
     */
    public static function listFolioINDirectionByMaster(
        string $MasterFolioNo,
        &$fromDt,
        &$toDt,
        &$dailyAmount,
        &$dailyTotal,
        &$INChargeType
    ) {
        // Table aliases (match your schema names if different)
        $dirTable = 'tblfoliodirection';
        $folioTable = 'tblfolio';

        $query = DB::table($dirTable)
            ->select(
                "{$dirTable}.ChargeTypeID",
                "{$dirTable}.FolioNo",
                "{$dirTable}.dirBegin",
                "{$dirTable}.dirEnd",
                "{$dirTable}.dailyAmount",
                "{$dirTable}.dailyTotal",
                "{$dirTable}.dMasterFolio"
            )
            ->join($folioTable, "{$folioTable}.FolioNo", '=', "{$dirTable}.FolioNo")
            ->where("{$dirTable}.dMasterFolio", $MasterFolioNo)
            ->where("{$folioTable}.stat", 1)
            ->groupBy("{$dirTable}.ChargeTypeID")
            ->groupBy("{$dirTable}.FolioNo")
            ->orderBy("{$dirTable}.FolioNo", 'asc');

        $rows = $query->get();

        if ($rows->isNotEmpty()) {
            $first = $rows->first();

            // Parse and set output references
            $fromDt = $first->dirBegin instanceof \DateTime ? $first->dirBegin : Carbon::parse($first->dirBegin);
            $toDt = $first->dirEnd instanceof \DateTime ? $first->dirEnd : Carbon::parse($first->dirEnd);
            $dailyAmount = is_numeric($first->dailyAmount) ? (float)$first->dailyAmount : 0.0;
            $dailyTotal = is_numeric($first->dailyTotal) ? (int)$first->dailyTotal : 0;

            // Build comma-separated ChargeTypeID list
            $chargeParts = $rows->map(function ($r) {
                return (int)$r->ChargeTypeID;
            })->unique()->values()->all();

            $INChargeType = implode(',', $chargeParts);
        }

        return $rows;
    }

    /**
     * Check whether transfer direction is allowed.
     *
     * @param string $NoTrans
     * @param string $ShareFolioNo
     * @param string $INChargeTypeID  Comma-separated list of ChargeType IDs (e.g. "1,2,3")
     * @param \DateTime|string $transDate
     * @param float $dailyAmount
     * @param int $dailyTotal
     * @param float $transAmount
     * @param float &$totalSum       (by reference) returns existing summed amount for the day / filters
     * @return bool
     */
    public static function isAllowedToTransferDirection(
        string $NoTrans,
        string $ShareFolioNo,
        string $INChargeTypeID,
        $transDate,
        float $dailyAmount,
        int $dailyTotal,
        float $transAmount,
        float &$totalSum = 0.0
    ): bool {
        // Normalize and parse charge type IDs into array (trim spaces, ignore empties)
        $chargeIds = array_filter(array_map('trim', explode(',', $INChargeTypeID)), function ($v) {
            return $v !== '';
        });

        if (empty($chargeIds)) {
            // No charge types specified => nothing to sum, allow transfer
            $totalSum = 0.0;
            if ($dailyTotal !== 0 && $transAmount > $dailyAmount) {
                return false;
            }
            return true;
        }

        // Normalize date string for whereDate
        $date = $transDate instanceof \DateTime ? $transDate->format('Y-m-d') : Carbon::parse($transDate)->format('Y-m-d');

        // Build base query
        $query = DB::table('tbltransd')
            ->selectRaw('SUM(amount) as total_amount, FolioNo')
            ->whereIn('ChargeTypeID', $chargeIds)
            ->where('shareFolioNo', $ShareFolioNo);

        if (!empty($NoTrans)) {
            $query->where('NoTrans', $NoTrans);
        }

        if ($dailyTotal !== 0) {
            $query->whereDate('transDate', $date);
        }

        $query->groupBy('FolioNo');

        $row = $query->first();

        if ($row && isset($row->total_amount) && is_numeric($row->total_amount)) {
            $totalSum = (float)$row->total_amount;
            if ($dailyTotal !== 0) {
                if (($totalSum + $transAmount) > (float)$dailyAmount) {
                    return false;
                }
            }
        } else {
            $totalSum = 0.0;
        }

        if ($dailyTotal !== 0) {
            if ($transAmount > (float)$dailyAmount) {
                return false;
            }
        }

        return true;
    }

    ///dynamically evaluate a mathematical formula string by replacing placeholders with actual numeric values and then computing the result
    function calcFormula(
        string $setVal,
        float $hu,
        float $onhand,
        float $avail,
        float $block,
        float $rate,
        float $oos,
        float $compl,
        float $ooi,
        float $totalroom,
        float $roomratebase
    ): float {
        // Replace placeholders with actual values
        $replacements = [
            '[HU]' => $hu,
            '[ONHAND]' => $onhand,
            '[AVAIL]' => $avail,
            '[BLOCK]' => $block,
            '[TOTAL_RATE]' => $rate,
            '[OOS]' => $oos,
            '[COMPL]' => $compl,
            '[COMP]' => $compl,
            '[OOI]' => $ooi,
            '[TOTALROOM]' => $totalroom,
            '[ROOMRATE]' => $rate,
            '[ROOMRATEBASE]' => $roomratebase,
        ];

        $expression = str_replace(array_keys($replacements), array_values($replacements), $setVal);

        // Evaluate the expression safely
        try {
            // Only allow math-safe characters
            if (!preg_match('/^[0-9+\-*/().\s]+$/', $expression)) {
                return 0.0;
            }

            // Evaluate using eval in a controlled scope
            $result = 0.0;
            eval('$result = ' . $expression . ';');

            return is_numeric($result) ? (float) $result : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    function isRateComplimentary($rateID): bool
    {
        $result = DB::table('tblrate')
            ->where('RateID', $rateID)
            ->value('isComplimentary');

        return (bool) $result;
    }


    function isRateHouse(int $rateID): bool
    {
        $result = DB::table('tblrate')
            ->where('RateID', $rateID)
            ->value('isHousePlan');

        return (bool) $result;
    }

    function saveChangeRateReserv(string $NoTrans, string $NoTransD, int $rateID, int $tariffID, string $compID, Date $arrivalDate, float $newRate, string &$msg, float $Exchange, string $CurrencyID): bool
    {
        // Step 1: Check reservation status
        $status = DB::table('tbltransreserv')
            ->where('NoTrans', $NoTrans)
            ->value('Stat');

        if ((int)$status === 4) {
            return false;
        }

        // Step 2: Validate rate type
        if (self::isRateComplimentary($rateID)) {
            $msg = "Rate is complimentary. Can't continue.";
            return false;
        }

        if (self::isRateHouse($rateID)) {
            $msg = "Rate is house use. Can't continue.";
            return false;
        }

        // Step 3: Check company rate
        $storeCTL = new StoreController();
        $compRate = $storeCTL->getRateByCompany($tariffID, $compID, $arrivalDate);
        if ($compRate >= 0) {
            $msg = "Company rate. Can't continue.";
            return false;
        }

        // Step 4: Calculate converted rate
        $ttl = self::getExchangeRate($CurrencyID, $newRate, $Exchange);

        // Step 5: Lookup bed value
        $bed = DB::table('tbltransdreserv')
            ->where('NoTransD', $NoTransD)
            ->value('bed');

        // Step 6: Prepare update data
        $updateData = [
            'oriamount'     => $newRate,
            'amount'        => $ttl,
            'total'         => $ttl,
            'rateChanged'   => 1,
            'useSurcharge'  => 0,
        ];

        if (is_numeric($bed) && (int)$bed !== 0) {
            $updateData['xtraBed'] = $ttl;
        }

        // Step 7: Execute update
        $updated = DB::table('tbltransdreserv')
            ->where('NoTransD', $NoTransD)
            ->update($updateData);

        if ($updated) {
            // Step 8: Lookup TariffID and save tariff details
            $tariffID = DB::table('tbltransdreserv')
                ->where('NoTransD', $NoTransD)
                ->value('TariffID');

            $storeCTL->saveTransDTariffReserv($NoTransD, $tariffID, $ttl, $newRate, false, 0, true);
            return true;
        }

        $msg = 'Update failed';
        return false;
    }

    //Saat klik show details di folio reservation
    function listTransDTariffReserv(string $notransD, ?string $onDate = null, string $FolioNo = ''): array
    {
        $baseCurrency = session('baseCurrency');

        // Step 1: Try to fetch from tblcustomrate if date and folio are provided
        if (!empty($onDate) && !empty($FolioNo)) {
            $customRates = DB::table('tblcustomrated')
                ->join('tblcustomrate', 'tblcustomrated.cRateID', '=', 'tblcustomrate.cRateID')
                ->join('tblchargetype', 'tblcustomrated.ChargeTypeID', '=', 'tblchargetype.ChargeTypeID')
                ->select([
                    'tblcustomrate.cRateID as nUrut',
                    'tblcustomrate.onDate',
                    'tblcustomrate.FolioNo',
                    'tblcustomrated.ChargeTypeID',
                    'tblchargetype.ChargeName',
                    'tblcustomrated.ChargeAmount as Jumlah',
                    'tblcustomrated.ChargeAmount as JumlahEqv',
                    DB::raw('0 as AP'),
                    'tblcustomrated.ChargeAmount as Pendapatan',
                    'tblcustomrated.BaseAmt',
                    'tblcustomrated.servAmt as Service',
                    'tblcustomrated.taxAmt as Tax',
                    'tblcustomrated.Pax as paxQty',
                    DB::raw("'$baseCurrency' as CurrencyID"),
                    DB::raw('0 as isFirstDayOnly'),
                    'tblchargetype.ChargeName as ChargeFirstDay',
                    DB::raw("'' as SupplierID"),
                    'tblchargetype.ChargeName as Description',
                    DB::raw("'FO' as KodeDiv"),
                    'tblcustomrated.ChargeTypeID as ChargeTypeIDFirstDay',
                ])
                ->whereDate('tblcustomrate.onDate', Carbon::parse($onDate)->format('Y-m-d'))
                ->where('tblcustomrate.FolioNo', $FolioNo)
                ->where('tblcustomrate.isActive', '<>', 0)
                ->get();

            if ($customRates->count() > 0) {
                return $customRates->toArray();
            }
        }

        // Step 2: Fallback to tbltransdtariffreserv
        $fallbackRates = DB::table('tbltransdtariffreserv')
            ->join('tblchargetype', 'tbltransdtariffreserv.ChargeTypeID', '=', 'tblchargetype.ChargeTypeID')
            ->leftJoin('tblchargetype as tblchargetype_1', 'tbltransdtariffreserv.ChargeTypeIDFirstDay', '=', 'tblchargetype_1.ChargeTypeID')
            ->select([
                'tbltransdtariffreserv.ChargeTypeID',
                'tblchargetype.ChargeName',
                'tbltransdtariffreserv.Description',
                'tbltransdtariffreserv.Jumlah',
                'tbltransdtariffreserv.JumlahEqv',
                'tbltransdtariffreserv.AP',
                'tbltransdtariffreserv.Pendapatan',
                'tbltransdtariffreserv.BaseAmt',
                'tbltransdtariffreserv.Service',
                'tbltransdtariffreserv.Tax',
                'tbltransdtariffreserv.SupplierID',
                'tbltransdtariffreserv.KodeDiv',
                'tbltransdtariffreserv.ChargeTypeIDFirstDay',
                'tblchargetype_1.ChargeName as ChargeFirstDay',
            ])
            ->where('tbltransdtariffreserv.notransd', $notransD)
            ->get();

        return $fallbackRates->toArray();
    }

    function allowOO(
        Carbon $fromDt,
        Carbon $toDt,
        int $roomNo,
        int $roomTypeID,
        &$dtIn,
        &$dtOut,
        &$gName,
        array $inRoom = [],
        bool $isOOI = false,
        bool $showMsg = true,
        &$msg = ''
    ): bool {
        $defaultActiDate = Carbon::parse(session('activeDate'));
        if ($roomNo !== 0) {
            $statusID = DB::table('tblroom')->where('roomNo', $roomNo)->value('statusID');

            if ($fromDt->isSameDay($defaultActiDate)) {
                if (in_array($statusID, [2, 3])) {
                    if ($showMsg) {
                        $msg .= "Room is not available ($roomNo).\n";
                    }
                    return false;
                }
            }

            if ($statusID === 1) {
                $msg .= "Room is not available ($roomNo > Invalid status).\n";
                return false;
            }
        }

        // Combine reservation, transaction, and multi-room usage
        $reservations = DB::table('tblreservation')
            ->select('NoReserv as NoTrans', 'CheckInDate', 'CheckOutDate', DB::raw("CONCAT_WS(' ', rFirstName, rLastName) AS GuestName"))
            ->where('roomNo', $roomNo)
            ->whereNotIn('Stat', [0, 4, 7, 8]);

        $transactions = DB::table('tbltrans')
            ->select('NoTrans', 'ArrivalDate as CheckInDate', 'DepartDate as CheckOutDate', DB::raw("CONCAT_WS(' ', tGuestName, tGuestLastName) AS GuestName"))
            ->where('roomNo', $roomNo)
            ->where('Stat', 5);

        $multiRoom = DB::table('tblmultiroom')
            ->select('NoTrans', 'FromDt as CheckInDate', 'ToDt as CheckOutDate', DB::raw("'Used by Multi Room' AS GuestName"))
            ->where('roomNo', $roomNo)
            ->whereDate('dtDepart', '>=', $fromDt);

        $combined = $reservations->unionAll($transactions)->unionAll($multiRoom)->get();

        foreach ($combined as $entry) {
            $inDt = Carbon::parse($entry->CheckInDate)->startOfDay();
            $outDt = Carbon::parse($entry->CheckOutDate)->startOfDay();

            if ($fromDt->equalTo($inDt) || ($fromDt->lt($inDt) && $toDt->gt($inDt)) || ($fromDt->gt($inDt) && $fromDt->lt($outDt))) {
                $gName = $entry->GuestName;
                $dtIn = $inDt;
                $dtOut = $outDt;
                return false;
            }
        }

        // Check if room is on hold
        if (self::isRoomHold($roomNo)) {
            if ($showMsg) {
                $msg .= "Room is in HOLD position ($roomNo).\n";
            }
            return false;
        }

        // Check room stock availability
        $rtype = $roomTypeID;
        $tblsetting = DB::table('tblsetting')->where('setActive', 1)->where('setName', 'allow_room_inv_minus_total')->first();
        if (isset($tblsetting) && $tblsetting->setValue == 1) {
            $rtype = 0;
        }
        $oDt = null;
        if (!self::isRoomStockAvailable2020($rtype, $roomNo, $fromDt, $toDt, $oDt)) {
            if ($showMsg) {
                $msg .= "2020: Room is not available ($roomNo) on " . $oDt->format('d-M-Y') . "\n";
            }
            return false;
        }

        // Check Out-of-Order list
        $oooList = DB::table('tblooolist')
            ->where('roomNo', $roomNo)
            ->get(['fromDate', 'toDate']);

        foreach ($oooList as $entry) {
            $fd = Carbon::parse($entry->fromDate)->startOfDay();
            $td = Carbon::parse($entry->toDate)->startOfDay();

            if ($fromDt->equalTo($fd) || ($fromDt->lt($fd) && $toDt->gt($fd)) || ($fromDt->gt($fd) && $fromDt->lt($td))) {
                if ($showMsg) {
                    $msg .= "Room is not available at that time period on OOOList.\n";
                }
                return false;
            }
        }

        return true;
    }

    // Set or unset hold status on a room. $holdStat=1 to set hold, 0 to unset.
    function setHold(int $roomTypeID, int $roomNo, int $holdStat): bool
    {
        $rNo = $roomNo;
        $rtype = $roomTypeID;
        $tblsetting = DB::table('tblsetting')->where('setActive', 1)->where('setName', 'allow_room_inv_minus_total')->first();
        if (isset($tblsetting) && $tblsetting->setValue == 1) {
            $rtype = 0;
        }
        $defaultActiDate = Carbon::parse(session('activeDate'));

        // Step 1: Check room stock availability
        $oDt = null;
        if (!self::isRoomStockAvailable2020($rtype, 0, $defaultActiDate, $defaultActiDate, $oDt)) {
            return false;
        }

        // Step 2: Check for upcoming reservation
        $gName = '';
        $dtIn = null;
        $dtOut = null;
        self::getReservationByLastCheckOut($rNo, $gName, $dtIn, $dtOut, true);

        if (!empty($gName)) {
            return false;
        }

        // Step 3: Check if room is allowed to be held
        if (!self::allowOO(request('startDate'), request('endDate'), $rNo, $roomTypeID, $dtIn, $dtOut, $gName)) {
            return false;
        }

        // Step 4: Check if room is out of service
        $isOOO = DB::table('tblroom')->where('roomNo', $rNo)->value('isRoomOOO');
        if ($isOOO) {
            return false;
        }

        // Step 5: Check if reservation exists for today
        $hasTodayReservation = DB::table('tblreservation')
            ->whereDate('CheckinDate', $defaultActiDate->format('Y-m-d'))
            ->where('roomNo', $rNo)
            ->whereNotIn('Stat', [0, 4, 7, 8])
            ->exists();

        if ($hasTodayReservation) {
            return false;
        }

        // Step 6: Check if room is in use
        $statusID = DB::table('tblroom')->where('roomNo', $rNo)->value('statusID');
        if ($statusID < 4) {
            return false;
        }

        // Step 7: Re-check stock if holdStat is not 0
        if ($roomTypeID !== 0 && $holdStat !== 0) {
            if (!self::isRoomStockAvailable2020($rtype, 0, $defaultActiDate, $defaultActiDate, $oDt)) {
                return false;
            }
        }

        // Step 8: Check if room is assigned to a transaction
        if ($roomNo !== 0 && $holdStat !== 0) {
            $hasActiveTrans = DB::table('tbltrans')
                ->where('roomNo', $roomNo)
                ->where('Stat', 5)
                ->exists();

            if ($hasActiveTrans) {
                return false;
            }
        }

        // Step 9: Update room hold status
        $updated = DB::table('tblroom')
            ->where('roomNo', $roomNo)
            ->whereIn('statusID', [5, 6])
            ->update([
                'isHold' => $holdStat,
                'isHoldRemark' => request('remark'),
                'isHoldBy' => strtoupper(auth()->user()->username ?? 'SYSTEM'),
            ]);

        return $updated > 0;
    }


    function settingLoadSet(string $setName, $defaultValue = null)
    {
        $value = DB::table('tbl_settingx')
            ->where('setName', $setName)
            ->where('setActive', '<>', 0)
            ->value('setValue');

        return $value ?? $defaultValue;
    }

    function saveOTAAvailable_TL(int $roomTypeID, Carbon $arrival, Carbon $departure, int $tariffID, int $rateid = 0, bool $removeFirst = true): bool
    {
        // Step 1: Check OTA update setting
        $setting = self::settingLoadSet('update_ota_availability');
        if (is_null($setting) || !boolval($setting)) {
            return false;
        }

        // Step 2: Validate arrival/departure dates
        $defaultActiDate = Carbon::parse(session('activeDate'));
        if ($arrival->lt($defaultActiDate)) {
            $arrival = $defaultActiDate->copy();
        }
        if ($departure->lte($arrival)) {
            return false;
        }

        // Step 3: Get rate ID from tariff
        if ($tariffID !== 0) {
            $rateid = DB::table('tbltariff')
                ->where('tariffID', $tariffID)
                ->value('rateid');
        }

        if (!is_numeric($rateid)) {
            return false;
        }

        // Step 4: Get rate code and room type shortcode
        $ratecode = DB::table('tblrate')
            ->where('RateID', $rateid)
            ->value('RateCode');

        $shortcode = DB::table('tblroomtype')
            ->where('roomTypeID', $roomTypeID)
            ->value('roomTypeShortCode');

        if (empty($shortcode)) {
            return false;
        }

        // Step 5: Check OTA inventory mapping
        $nid = DB::table('tblotainventorycode')
            ->where('invCode', $shortcode)
            ->where('RateCode', $ratecode)
            ->value('nUrut');

        if (!is_numeric($nid)) {
            $nid = DB::table('tblotainventorycode')
                ->where('invCode', $shortcode)
                ->value('nUrut');
            $ratecode = '';
            if (!is_numeric($nid)) {
                return false;
            }
        }

        $ratecode = DB::table('tblotainventorycode')
            ->where('nUrut', $nid)
            ->value('RateCodeOTA');

        // Step 6: Extend departure date by 10 days
        $departure = $departure->copy()->addDays(10);

        // Step 7: Remove old records if needed
        if ($removeFirst) {
            DB::table('tblotaavailable_tl')
                ->where('isProcessed', '<>', 0)
                ->delete();

            DB::table('tblotaavailable_tl')
                ->whereDate('CreatedOn', '<=', $defaultActiDate->copy()->subDays(5)->format('Y-m-d'))
                ->delete();
        }

        // Step 8: Insert new OTA availability record
        $inserted = DB::table('tblotaavailable_tl')->insertOrIgnore([
            'Arrival'       => $arrival->format('Y-m-d'),
            'Departure'     => $departure->format('Y-m-d'),
            'roomTypeID'    => $roomTypeID,
            'InvTypeCode'   => $shortcode,
            'RatePlanCode'  => $ratecode,
        ]);

        return $inserted;
    }

    function insertRoomStatLog(int $roomNo, int $oldStat, int $newStat, bool $oldInspect = false, bool $newInspect = false): bool
    {
        $userID = auth()->id() ?? 'SYSTEM';
        $userPC = request()->ip() . '@' . gethostname();

        $inserted = DB::table('tblroomstatlog')->insert([
            'byUserID'    => $userID,
            'byUserPC'    => $userPC,
            'newStat'     => (int) $newStat,
            'oldStat'     => (int) $oldStat,
            'roomNo'      => $roomNo,
            'oldInspect'  => $oldInspect,
            'newInspect'  => $newInspect,
        ]);

        return $inserted;
    }

    function insertIntoRoomStock(
        string $NoTrans,
        int $roomNo,
        Carbon $fromDt,
        Carbon $toDt,
        bool $isRemove = false,
        bool $isOO = false,
        bool $removeFirst = false,
        bool $isOOO = false
    ): bool {
        // Step 1: Lookup roomTypeID
        $rmTypeID = DB::table('tblroom')->where('roomNo', $roomNo)->value('roomTypeID');

        if (!$isRemove) {
            $dtDiff = $fromDt->diffInDays($toDt);
            $batch = [];

            // Step 2: Remove existing records if needed
            if ($removeFirst) {
                if ($isOO) {
                    DB::table('tblroomstocknew')
                        ->where('roomNo', $roomNo)
                        ->where('roomTypeID', $rmTypeID)
                        ->where('isOO', 1)
                        ->where('NoTrans', $NoTrans)
                        ->delete();
                } elseif ($isOOO) {
                    DB::table('tblroomstocknew')
                        ->where('roomNo', $roomNo)
                        ->where('roomTypeID', $rmTypeID)
                        ->where('isOOO', 1)
                        ->where('NoTrans', $NoTrans)
                        ->delete();
                }
            }

            // Step 3: Prepare insert batch
            for ($i = 0; $i <= $dtDiff; $i++) {
                $stayDate = $fromDt->copy()->addDays($i)->format('Y-m-d');
                $record = [
                    'ClientID'     => '',
                    'NoTrans'      => $NoTrans,
                    'roomNo'       => $roomNo,
                    'roomTypeID'   => $rmTypeID,
                    'stayDate'     => $stayDate,
                    'transTypeID'  => 1,
                ];

                if ($isOO) {
                    $record['isOO'] = 1;
                }
                if ($isOOO) {
                    $record['isOOO'] = 1;
                }
                if ($stayDate === $toDt->format('Y-m-d')) {
                    $record['isStay'] = 0;
                }

                $batch[] = $record;

                // Step 4: Chunk insert every 50 records
                if (count($batch) >= 50) {
                    DB::table('tblroomstocknew')->insert($batch);
                    $batch = [];
                }
            }

            // Step 5: Final insert
            if (!empty($batch)) {
                DB::table('tblroomstocknew')->insert($batch);
            }

            return true;
        } else {
            // Step 6: Handle deletion
            $query = DB::table('tblroomstocknew')->where('roomNo', $roomNo)->where('roomTypeID', $rmTypeID);

            if ($isOO) {
                $query->where('isOO', 1)->where('NoTrans', $NoTrans);
            } elseif ($isOOO) {
                $query->where('isOOO', 1)->where('NoTrans', $NoTrans);
            } else {
                $query->where('NoTrans', '')->where('ClientID', '');
            }

            return $query->delete() > 0;
        }
    }

    function insertOOOList(int $roomNo, int $roomTypeID, bool $isOOO, Carbon $fromDt, Carbon $toDt, string $remark, &$insertID = 0): bool
    {
        $createdBy = auth()->user()->username ?? 'SYSTEM';

        $data = [
            'createdBy'   => $createdBy,
            'fromDate'    => $fromDt->format('Y-m-d'),
            'toDate'      => $toDt->format('Y-m-d'),
            'isOOO'       => $isOOO ? 1 : 0,
            'Remark'      => $remark,
            'roomNo'      => $roomNo,
            'roomTypeID'  => $roomTypeID,
        ];

        $insertID = DB::table('tblooolist')->insertGetId($data);

        return $insertID > 0;
    }


    //Set room as OOI or remove from OOI
    public static function setRoomOOO(
        $roomNo,
        $roomTypeID,
        $fromDt,
        $toDt,
        $isOOO,
        $remark,
        $formName,
        $isRemove,
        &$msg = "",
        $showMsg = true,
        $doTransaction = true,
        $checkOOOIList = true
    ) {
        // Initialize variables
        $valOOO = $isOOO ? 1 : 0;
        $nTrans = "";
        $nUrut = 0;

        // Get room type ID if not provided
        if ($roomTypeID == 0) {
            $roomTypeID = DB::table('tblroom')
                ->where('roomNo', $roomNo)
                ->value('roomTypeID');
        }

        $oldInspect = DB::table('tblroom')
            ->where('roomNo', $roomNo)
            ->value('isInspect');

        if (!$isRemove) {
            // Set OOO process
            $stt = DB::table('tblroom')
                ->where('roomNo', $roomNo)
                ->value('statusID');

            // Validate dates
            if (!$fromDt || !Carbon::parse($fromDt)->isValid()) {
                if ($showMsg) {
                    $msg = $roomNo . " Invalid date value.";
                    // In Laravel, you might want to use session flash or return with error
                    // session()->flash('error', $msg);
                }
                $msg = $roomNo . " Invalid date value.";
                return false;
            }

            if (!$toDt || !Carbon::parse($toDt)->isValid()) {
                if ($showMsg) {
                    $msg = $roomNo . " Invalid date value.";
                    // session()->flash('error', $msg);
                }
                $msg = $roomNo . " Invalid date value.";
                return false;
            }

            $fromDate = Carbon::parse($fromDt)->startOfDay();
            $toDate = Carbon::parse($toDt)->startOfDay();

            if ($toDate->lt($fromDate)) {
                if ($showMsg) {
                    $msg = $roomNo . " Invalid date range value.";
                    // session()->flash('error', $msg);
                }
                $msg = $roomNo . " Invalid date range value.";
                return false;
            }

            // Start transaction if needed
            if ($doTransaction) {
                DB::beginTransaction();
            }

            try {
                // Check if room already exists in OOO list
                $existingOOO = DB::table('tblooolist')
                    ->where('roomNo', $roomNo)
                    ->exists();

                if ($existingOOO) {
                    if ($doTransaction) {
                        DB::rollBack();
                    }
                    if ($showMsg) {
                        $msg = $roomNo . " Room is not available. Already set as OOS/OOI in another date.";
                        // session()->flash('error', $msg);
                    }
                    $msg = $roomNo . " Room is not available. Already set as OOS/OOI in another date.";
                    return false;
                }

                // Check room availability (placeholder for DALhotel::allowOO equivalent)
                $dtIn = null;
                $dtOut = null;
                $gName = "";

                // You'll need to implement the allowOO equivalent logic here
                if (!self::allowOO($fromDate, $toDate, $roomNo, $roomTypeID, $dtIn, $dtOut, $gName)) {
                    if ($doTransaction) {
                        DB::rollBack();
                    }
                    if (!empty($gName)) {
                        if ($showMsg) {
                            $msg = $roomNo . " Can not continue. Next Expected arrival will be on : " . $dtIn->format('d-M-Y') . ".";
                            // session()->flash('error', $msg);
                        }
                        $msg = $roomNo . " Can not continue. Next Expected arrival will be on : " . $dtIn->format('d-M-Y') . ".";
                    }
                    return false;
                }

                $reservCTL = new ReservationController();
                // Check if room is OOS/OOI for selected dates (placeholder for DALTrans equivalent)
                if ($reservCTL->isRoomOOODate($roomNo, $fromDate, $toDate)) {
                    if ($doTransaction) {
                        DB::rollBack();
                    }
                    if ($showMsg) {
                        $msg = $roomNo . " Room is Out of Service for selected date.";
                        // session()->flash('error', $msg);
                    }
                    $msg = $roomNo . " Room is Out of Service for selected date.";
                    return false;
                }

                if ($reservCTL->isRoomOOIDate($roomNo, $fromDate, $toDate)) {
                    if ($doTransaction) {
                        DB::rollBack();
                    }
                    if ($showMsg) {
                        $msg = $roomNo . " Room is Out of Inventory for selected date.";
                        // session()->flash('error', $msg);
                    }
                    $msg = $roomNo . " Room is Out of Inventory for selected date.";
                    return false;
                }

                // Check room stock availability
                $rtype = $roomTypeID;
                // Assuming BOOL_AllowRoomInvMinusTotal is defined somewhere
                $tblsetting = DB::table('tblsetting')->where('setActive', 1)->where('setName', 'allow_room_inv_minus_total')->first();
                if (isset($tblsetting) && $tblsetting->setValue == 1) {
                    $rtype = 0;
                }

                $defaultActiDate = Carbon::now(); // You might want to define this properly

                if ($fromDate->isValid() && $toDate->isValid()) {
                    $dayDiff = $fromDate->diffInDays($toDate);

                    $oDt = null;
                    if (!self::isRoomStockAvailable2020($rtype, 0, $fromDate, $toDate, $oDt)) {
                        if ($doTransaction) {
                            DB::rollBack();
                        }
                        $msg = "2020: Can not continue. Room is not available at this time: " . $roomNo;
                        return false;
                    }
                } else {
                    $oDt = null;
                    if (!self::isRoomStockAvailable2020($rtype, 0, $defaultActiDate, $defaultActiDate, $oDt)) {
                        if ($doTransaction) {
                            DB::rollBack();
                        }
                        $msg = "2020: Can not continue. Room is not available at this time: " . $roomNo;
                        return false;
                    }
                }

                // Insert into OOO list
                $idd = 0;
                if (!self::insertOOOList($roomNo, $roomTypeID, $isOOO, $fromDate, $toDate, $remark, $idd)) {
                    if ($doTransaction) {
                        DB::rollBack();
                    }
                    return false;
                }

                $nTrans = DB::table('tblooolist')->insertGetId([]) . $roomNo;

                // Insert into room stock
                if (!self::insertIntoRoomStock($nTrans, $roomNo, $fromDate, $toDate, false, !$isOOO, false, $isOOO)) {
                    if ($doTransaction) {
                        DB::rollBack();
                    }
                    return false;
                }

                // Update room table
                $updateData = [];
                if ($isOOO) {
                    $updateData = [
                        'isRoomOOO' => 1,
                        'roomOOOStart' => $fromDate,
                        'roomOOOEnd' => $toDate,
                        'isRoomOOOBy' => auth()->user()->name ?? 'System', // Assuming you have authentication
                        'roomOOORemark' => $remark,
                    ];
                } else {
                    $updateData = [
                        'statusID' => 4, // Assuming roomStat.OutOfInventory = 4
                        'statusStart' => $fromDate,
                        'statusEnd' => $toDate,
                        'statusRemark' => $remark,
                    ];
                }
                $updateData['isInspect'] = 0;

                $updated = DB::table('tblroom')
                    ->where('roomNo', $roomNo)
                    ->update($updateData);

                if (!$updated) {
                    if ($doTransaction) {
                        DB::rollBack();
                    }
                    return false;
                }

                // Insert room status log
                if ($isOOO) {
                    self::insertRoomStatLog($roomNo, $stt, 3, $oldInspect, 0); // Assuming roomStat.OutOfOrder = 3
                } else {
                    self::insertRoomStatLog($roomNo, $stt, 4, $oldInspect, 0); // Assuming roomStat.OutOfInventory = 4
                }

                // Save OTA availability
                self::saveOTAAvailable_TL($roomTypeID, $fromDate, $toDate, 0);

                if ($doTransaction) {
                    DB::commit();
                }

                return true;
            } catch (\Exception $e) {
                if ($doTransaction) {
                    DB::rollBack();
                }
                Log::error('Error setting room OOO: ' . $e->getMessage());
                $msg = "Error setting room OOO: " . $e->getMessage();
                return false;
            }
        } else {
            // Remove OOO process
            try {
                if (!$fromDt || !Carbon::parse($fromDt)->isValid()) {
                    $roomData = DB::table('tblroom')
                        ->where('roomNo', $roomNo)
                        ->first();

                    if ($roomData) {
                        if ($isOOO) {
                            if (!$roomData->roomOOOStart || !Carbon::parse($roomData->roomOOOStart)->isValid()) {
                                if ($doTransaction && $showMsg) {
                                    $msg = $roomNo . " Room OOS Start is not a date.";
                                    // session()->flash('error', $msg);
                                }
                                $msg = $roomNo . " Room OOS Start is not a date.";
                                return false;
                            }
                            if ($roomData->isRoomOOO == 0) {
                                if ($showMsg) {
                                    $msg = $roomNo . " Room is not OOS.";
                                    // session()->flash('error', $msg);
                                }
                                $msg = $roomNo . " Room is not OOS.";
                                return false;
                            }
                            $fromDt = Carbon::parse($roomData->roomOOOStart)->startOfDay();
                            $toDt = Carbon::parse($roomData->roomOOOEnd)->startOfDay();
                        } else {
                            if (!$roomData->statusStart || !Carbon::parse($roomData->statusStart)->isValid()) {
                                if ($doTransaction && $showMsg) {
                                    $msg = $roomNo . " Room OOI Start is not a date.";
                                    // session()->flash('error', $msg);
                                }
                                $msg = $roomNo . " Room OOI Start is not a date.";
                                return false;
                            }
                            if ($roomData->statusID != 4) { // Assuming roomStat.OutOfInventory = 4
                                if ($showMsg) {
                                    $msg = $roomNo . " Room is not OOI.";
                                    // session()->flash('error', $msg);
                                }
                                $msg = $roomNo . " Room is not OOI.";
                                return false;
                            }
                            $fromDt = Carbon::parse($roomData->statusStart)->startOfDay();
                            $toDt = Carbon::parse($roomData->statusEnd)->startOfDay();
                        }

                        if (!$isRemove) {
                            $s = DB::table('tblooolist')
                                ->where('roomNo', $roomNo)
                                ->where('isOOO', $valOOO)
                                ->whereDate('fromDate', $fromDt->format('Y-m-d'))
                                ->whereDate('toDate', $toDt->format('Y-m-d'))
                                ->value('nUrut');

                            if (!is_numeric($s)) {
                                if ($showMsg) {
                                    $msg = $roomNo . " Room is not OOI/OOS.";
                                    // session()->flash('error', $msg);
                                }
                                $msg = $roomNo . " Room is not OOI/OOS.";
                                return false;
                            }
                            $nUrut = (int)$s;
                        }
                    }
                } else {
                    $oooData = DB::table('tblooolist')
                        ->where('roomNo', $roomNo)
                        ->where('isOOO', $isOOO ? '<>' : '=', 0)
                        ->whereDate('fromDate', Carbon::parse($fromDt)->format('Y-m-d'))
                        ->whereDate('toDate', Carbon::parse($toDt)->format('Y-m-d'))
                        ->first();

                    if (!$oooData) {
                        if ($isOOO) {
                            if ($showMsg) {
                                $msg = $roomNo . " Room is not OOS.";
                                // session()->flash('error', $msg);
                            }
                            $msg = $roomNo . " Room is not OOS.";
                        } else {
                            if ($showMsg) {
                                $msg = $roomNo . " Room is not OOI.";
                                // session()->flash('error', $msg);
                            }
                            $msg = $roomNo . " Room is not OOI.";
                        }
                        return false;
                    } else {
                        $nUrut = $oooData->nUrut;
                        $nTrans = $nUrut . $roomNo;
                    }
                }

                $endDate = Carbon::now()->startOfDay(); // defaultActiDate equivalent
                $statusEnd = DB::table('tblroom')
                    ->where('roomNo', $roomNo)
                    ->value('statusEnd');

                if ($statusEnd && Carbon::parse($statusEnd)->isValid()) {
                    $endDate = Carbon::parse($statusEnd)->startOfDay();
                }

                $roomOOOEnd = DB::table('tblroom')
                    ->where('roomNo', $roomNo)
                    ->value('roomOOOEnd');

                if ($roomOOOEnd && Carbon::parse($roomOOOEnd)->isValid()) {
                    $roomOOOEndDate = Carbon::parse($roomOOOEnd)->startOfDay();
                    if ($roomOOOEndDate->gt($endDate)) {
                        $endDate = $roomOOOEndDate;
                    }
                }

                $st = DB::table('tblroom')
                    ->where('roomNo', $roomNo)
                    ->value('statusID');

                $updateData = [];
                if ($isOOO) {
                    $updateData = [
                        'isRoomOOO' => 0,
                        'roomOOOEnd' => null,
                        'roomOOOStart' => null,
                        'roomOOORemark' => null,
                    ];

                    if (in_array($st, [5, 6])) { // Assuming these are specific status codes
                        $updateData['statusID'] = RoomStat::VacantDirty->value; // Assuming roomStat.VaccantDirty = 1
                        $updateData['VDRemark'] = 'Via OOS';
                    }
                } else {
                    if (in_array($st, [5, 6, 4])) { // Assuming these are specific status codes
                        $updateData['statusID'] = RoomStat::VacantDirty->value; // Assuming roomStat.VaccantDirty = 1
                    }
                    $updateData = array_merge($updateData, [
                        'statusEnd' => null,
                        'statusStart' => null,
                        'isDirtyOO' => 1,
                        'statusRemark' => null,
                        'VDRemark' => 'Via OOI',
                    ]);
                }

                if ($doTransaction) {
                    DB::beginTransaction();
                }

                $updated = DB::table('tblroom')
                    ->where('roomNo', $roomNo)
                    ->update($updateData);

                if ($updated) {
                    if ($nUrut == 0) {
                        $s = DB::table('tblooolist')
                            ->where('roomNo', $roomNo)
                            ->where('isOOO', $valOOO)
                            ->whereDate('fromDate', Carbon::parse($fromDt)->format('Y-m-d'))
                            ->whereDate('toDate', Carbon::parse($toDt)->format('Y-m-d'))
                            ->value('nUrut');

                        if (!is_numeric($s)) {
                            if ($showMsg) {
                                $msg = $roomNo . " Room is not OOI/OOS.";
                                // session()->flash('error', $msg);
                            }
                            $msg = $roomNo . " Room is not OOI/OOS.";
                            return false;
                        }
                        $nUrut = (int)$s;
                    }

                    // Delete from OOO list
                    $deleted = DB::table('tblooolist')
                        ->where('nUrut', $nUrut)
                        ->delete();

                    if ($deleted) {
                        if (self::insertIntoRoomStock($nUrut . $roomNo, $roomNo, now(), now(), true, !$isOOO, false, $isOOO)) {
                            if ($isOOO) {
                                self::insertRoomStatLog($roomNo, 3, 1, $oldInspect, 0); // OutOfOrder to VaccantDirty
                            } else {
                                self::insertRoomStatLog($roomNo, $st, 1, $oldInspect, 0); // Current status to VaccantDirty
                            }

                            $roomTypeID = DB::table('tblroom')
                                ->where('roomNo', $roomNo)
                                ->value('roomTypeID');

                            self::saveOTAAvailable_TL($roomTypeID, Carbon::now()->startOfDay(), $endDate, 0);

                            if ($doTransaction) {
                                DB::commit();
                            }
                            return true;
                        }
                    }
                }

                if ($doTransaction) {
                    DB::rollBack();
                }
                return false;
            } catch (\Exception $e) {
                if ($doTransaction) {
                    DB::rollBack();
                }
                Log::error('Error removing room OOO: ' . $e->getMessage());
                $msg = "Error removing room OOO: " . $e->getMessage();
                return false;
            }
        }
    }

    //Check if company rate is available for given company on selected date
    function isCompanyRateAvail(Carbon $dtToCheck, string $CompID, int $roomTypeID, string $CryID): bool
    {
        if (empty($CompID)) {
            return false;
        }

        // Step 1: Build subquery for valid tariff IDs
        $subQuery = DB::table('tbltariff')
            ->join('tblseason', 'tbltariff.seasonID', '=', 'tblseason.seasonID')
            ->where('tbltariff.roomTypeID', $roomTypeID)
            ->where('tbltariff.CurrencyID', $CryID)
            ->where('tblseason.toDate', '>=', $dtToCheck->format('Y-m-d'))
            ->where('tbltariff.isActive', '<>', 0)
            ->whereIn('tbltariff.tariffID', function ($query) use ($roomTypeID, $CryID) {
                $query->select(DB::raw('MAX(tariffID)'))
                    ->from('tbltariff')
                    ->where('roomTypeID', $roomTypeID)
                    ->where('CurrencyID', $CryID)
                    ->where('isActive', '<>', 0)
                    ->groupBy('rateid', 'seasonID', 'tSegmentID', 'packagePax', 'roomTypeID');
            });

        // Step 2: Join with tbl_tariffcompany
        $result = DB::table('tbltariffcompany')
            ->joinSub($subQuery, 'SubQuery', function ($join) {
                $join->on('tbltariffcompany.tariffID', '=', 'SubQuery.tariffID');
            })
            ->where('tbltariffcompany.CompID', $CompID)
            ->select('tbltariffcompany.CompID', 'SubQuery.tariffID')
            ->first();

        return !is_null($result);
    }

    function isGuestInHouse(string $NoTrans, Carbon $onDate, bool $isByMonth = false, bool $isByYear = false): bool
    {
        $result = DB::table('tbltrans')
            ->select('NoTrans', 'ArrivalDate', DB::raw('IFNULL(checkoutDate, DepartDate) AS CODate'))
            ->where('NoTrans', $NoTrans)
            ->where('Stat', '>=', 5)
            ->first();

        if (!$result || !Carbon::parse($result->CODate)->isValid()) {
            return false;
        }

        $arrivalDate = Carbon::parse($result->ArrivalDate)->startOfDay();
        $coDate = Carbon::parse($result->CODate)->startOfDay();

        if ($coDate->equalTo($arrivalDate) && $coDate->equalTo($onDate->startOfDay())) {
            return true;
        }

        if (!$isByMonth && !$isByYear) {
            $days = $arrivalDate->diffInDays($coDate) ?: 1;
            for ($i = 0; $i < $days; $i++) {
                if ($arrivalDate->copy()->addDays($i)->isSameDay($onDate)) {
                    return true;
                }
            }
        } elseif ($isByMonth) {
            $months = $arrivalDate->diffInMonths($coDate) ?: 1;
            for ($i = 0; $i < $months; $i++) {
                if ($arrivalDate->copy()->addMonths($i)->month === $onDate->month) {
                    return true;
                }
            }
        } elseif ($isByYear) {
            $years = $arrivalDate->diffInYears($coDate) ?: 1;
            for ($i = 0; $i < $years; $i++) {
                if ($arrivalDate->copy()->addYears($i)->year === $onDate->year) {
                    return true;
                }
            }
        }

        return false;
    }



    function getFolioDetails(string $FolioNo): array
    {
        $result = DB::table('tbltrans')
            ->join('tblfoliomaster', 'tblfoliomaster.mNoTrans', '=', 'tbltrans.NoTrans')
            ->join('tblfolio', 'tblfolio.FolioNo', '=', 'tblfoliomaster.mFolioNo')
            ->join('tbluser', 'tbluser.userID', '=', 'tblfolio.CheckOutBy')
            ->select([
                'tblfoliomaster.mFolioNo as FolioNo',
                'tbltrans.roomNo',
                'tbltrans.tariffID',
                'tbltrans.NoTrans',
                'tbltrans.tReservNo',
                'tbltrans.checkoutDate',
                'tbltrans.CurrencyID',
                DB::raw("CONCAT_WS(' ', tbltrans.tGuestName, tbltrans.tGuestLastName) AS GuestName"),
                'tbltrans.ArrivalDate',
                'tbltrans.DepartDate',
                'tbluser.userFullName as Username',
                DB::raw("IF(tblfolio.stat = 1, 'In House', IF(tblfolio.stat = 7, 'Checked Out', 'Cancel')) AS StatName"),
                'tblfolio.stat',
            ])
            ->where('tblfoliomaster.mFolioNo', $FolioNo)
            ->get();

        return $result->toArray();
    }

    public function getGuestNote(Request $request)
    {
        $clientID = $request->clientID ?? 'CYU00001';
        $modeViewData = $request->modeViewData;

        // Tentukan noteTypeID yang mau dikecualikan
        $excludedType = match ($modeViewData) {
            'registration' => [3, 4],  // Exclude Registration, (2)
            'cashier'      => [2, 4],  // Exclude Registration & Reservation (3)
            'reservation'  => [2, 3],  // Exclude Reservation (4)
            default        => [],
        };

        // Bangun query - GUNAKAN nUrut sebagai primary key
        $query = DB::table('tblclient_note')
            ->select(['nUrut', 'clientID', 'noteTypeID', 'Note'])
            ->where('clientID', '=', $clientID);

        if ($excludedType) {
            $query->whereNotIn('noteTypeID', $excludedType);
        }

        $data = $query->get();

        return response()->json([
            'status' => 'success',
            'data'   => $data
        ]);
    }

    public function saveGuestNote(Request $request)
    {
        $clientID = $request->clientID;
        $toInsert = collect($request->toInsert ?? []);
        $toUpdate = collect($request->toUpdate ?? []);
        $toDelete = collect($request->toDelete ?? []);
        $user = session('username') ?? 'SYSTEM';
        $now = now();

        DB::beginTransaction();
        try {
            $insertCount = 0;
            $updateCount = 0;
            $deleteCount = 0;

            // 🔹 1. INSERT - Data baru
            if ($toInsert->isNotEmpty()) {
                $insertData = $toInsert->map(function ($note) use ($clientID, $user, $now) {
                    return [
                        'clientID' => $clientID,
                        'noteTypeID' => $note['noteTypeID'] ?? null,
                        'Note' => trim($note['Note'] ?? ''),
                        'createdBy' => $user,
                        'createdOn' => $now,
                        'updatedBy' => $user,
                        'updatedOn' => $now
                    ];
                })->filter(function ($note) {
                    // Filter out empty or invalid notes
                    return $note['noteTypeID'] && $note['Note'] !== '';
                })->toArray();

                if (!empty($insertData)) {
                    DB::table('tblclient_note')->insert($insertData);
                    $insertCount = count($insertData);
                }
            }

            // 🔹 2. UPDATE - Data yang berubah (by nUrut)
            if ($toUpdate->isNotEmpty()) {
                foreach ($toUpdate as $note) {
                    $nUrut = $note['nUrut'] ?? null;
                    $noteTypeID = $note['noteTypeID'] ?? null;
                    $noteText = trim($note['Note'] ?? '');

                    if ($nUrut && $noteTypeID && $noteText !== '') {
                        $affected = DB::table('tblclient_note')
                            ->where('nUrut', $nUrut)
                            ->where('clientID', $clientID) // safety check
                            ->update([
                                'noteTypeID' => $noteTypeID,
                                'Note' => $noteText,
                                'updatedBy' => $user,
                                'updatedOn' => $now
                            ]);

                        if ($affected > 0) {
                            $updateCount++;
                        }
                    }
                }
            }

            // 🔹 3. DELETE - Data yang dihapus (by nUrut)
            if ($toDelete->isNotEmpty()) {
                $nUrutList = $toDelete->pluck('nUrut')->filter()->toArray();

                if (!empty($nUrutList)) {
                    $deleteCount = DB::table('tblclient_note')
                        ->where('clientID', $clientID)
                        ->whereIn('nUrut', $nUrutList)
                        ->delete();
                }
            }

            DB::commit();

            // Build response message
            $operations = [];
            if ($insertCount > 0) $operations[] = "Inserted: $insertCount";
            if ($updateCount > 0) $operations[] = "Updated: $updateCount";
            if ($deleteCount > 0) $operations[] = "Deleted: $deleteCount";

            return response()->json([
                'status' => 'success',
                'message' => !empty($operations)
                    ? 'Notes saved successfully. ' . implode(', ', $operations)
                    : 'No changes detected'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Save Guest Note Error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save notes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changeRate(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'NoTransD' => 'required',
            'NewRate' => 'required|numeric|min:0'
        ]);

        try {
            $noTransD = $request->NoTransD;
            $newRate = $request->NewRate;

            // Ambil data existing untuk hitung ulang surcharge dll
            $existingData = DB::table('tbltransdreserv')
                ->where('NoTransD', $noTransD)
                ->first();

            if (!$existingData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data not found'
                ]);
            }

            // dd($existingData);

            // Update data di table yang benar
            $updated = DB::table('tbltransdreserv')
                ->where('NoTransD', $noTransD)
                ->update([
                    'oriamount' => $newRate,
                    'amount' => $newRate,
                    'total' => $newRate
                    // Tambah field lain jika perlu
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Rate updated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No records updated'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function getTransactionDetails(Request $request)
    {
        $request->validate([
            'NoTransH' => 'required',
            'NoTransD' => 'required'
        ]);

        try {
            $noTransH = $request->NoTransH;
            $noTransD = $request->NoTransD;

            $data = DB::table('tbltransdtariffreserv')
                ->select([
                    'tbltransdtariffreserv.*', // Select semua field dari tbltransdtariffreserv
                    // Field dari join tables
                    'tblrate.RateName',
                    'tblseason.seasonName',
                    'tblsegment.segmentName',
                    'tblchargetype.ChargeName',
                    // ✅ Format RateName custom TANPA [tariffID]
                    DB::raw("CONCAT(tblrate.RateName, ' (', tblseason.seasonName, ' - ', tblsegment.segmentName, ')') AS RateNameFormatted")
                ])
                ->join('tblchargetype', 'tblchargetype.ChargeTypeID', '=', 'tbltransdtariffreserv.ChargeTypeID')
                ->join('tbltariff', 'tbltariff.tariffID', '=', 'tbltransdtariffreserv.tariffIDd')
                ->join('tblrate', 'tblrate.rateid', '=', 'tbltariff.rateid')
                ->join('tblseason', 'tblseason.seasonID', '=', 'tbltariff.seasonID')
                ->join('tblsegment', 'tblsegment.segmentID', '=', 'tbltariff.tSegmentID')
                ->where('tbltransdtariffreserv.NoTransH', '=', $noTransH)
                ->where('tbltransdtariffreserv.NoTransD', '=', $noTransD)
                ->get();

            if ($data) {
                return response()->json([
                    'success' => true,
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction details not found for NoTransH: ' . $noTransH . ' and NoTransD: ' . $noTransD
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function getDefaultCompany()
    {
        return DB::table("tblcomp")
            ->select([
                'tblcomp.defaultCompanyID',
                'tblcompany.CompID',
                'tblcompany.CompName',
                'tblcompany.compGroupID',
                'tblcompanygroup.cGroupID',
                'tblcompanygroup.cGroupName'
            ])
            ->join('tblcompany', 'tblcompany.CompID', '=', 'tblcomp.defaultCompanyID')
            ->join('tblcompanygroup', 'tblcompanygroup.cGroupID', '=', 'tblcompany.compGroupID')
            ->first();
    }

    public function getDefaultSetting()
    {
        return DB::table('tblcomp')
            ->select('*')
            ->first();
    }
}
