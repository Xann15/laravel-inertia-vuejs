<?php

namespace App\Http\Controllers;

use App\Services\PublicServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FieldController extends Controller
{
    public function getAllGuest(Request $request)
    {
        $skip = (int)$request->input('skip', 0);
        $take = (int)$request->input('take', 10);
        $searchValue = $request->input('searchValue');
        $fields = $request->input('fields') ? explode(',', $request->input('fields')) : [];

        $query = DB::table('tblclient')
            ->leftJoin('tblcountry', 'tblcountry.CountryID', 'tblclient.CountryID')
            ->leftJoin('tblcompany', 'tblcompany.CompID', 'tblclient.CompID')
            ->leftJoin('tblcompanygroup', 'tblcompanygroup.cGroupID', '=', 'tblcompany.compGroupID')  // join tambahan
            ->where('tblclient.cIsActive', 1)
            ->select(
                'tblclient.clientID',
                DB::raw("CONCAT(tblclient.FirstName, ' ', tblclient.LastName) as GuestName"),
                'tblclient.TypeID',
                'tblclient.TypeIDNumber',
                'tblclient.Phone',
                'tblclient.Address',
                'tblclient.Language',
                'tblclient.City',
                'tblclient.Email',
                'tblclient.BirthDate',
                'tblcountry.CountryID',
                'tblcountry.CountryName',
                'tblcompany.CompID',
                'tblcompany.CompName',
                'tblcompanygroup.cGroupName', // ditambahkan di select
                'tblclient.cBlackList'
            );

        // dd($query);


        // dd($query);
        if ($searchValue && !empty($fields)) {
            $query->where(function ($q) use ($fields, $searchValue) {
                foreach ($fields as $field) {
                    if ($field === 'GuestName') {
                        $q->orWhereRaw("CONCAT(tblclient.FirstName, ' ', tblclient.LastName) LIKE ?", ["%{$searchValue}%"]);
                    } else {
                        $q->orWhere($field, 'like', "%{$searchValue}%");
                    }
                }
            });
        }

        if ($request->has('filter')) {
            $filter = json_decode($request->input('filter'), true);

            // Handle filter sederhana (bukan grup OR)
            if (is_array($filter) && count($filter) === 3 && is_string($filter[0])) {
                [$field, $operator, $value] = $filter;
                $operator = strtolower($operator);

                if ($operator === 'contains') {
                    if ($field === 'GuestName') {
                        $query->where(function ($q) use ($value) {
                            $q->where('tblclient.FirstName', 'LIKE', "%{$value}%")
                                ->orWhere('tblclient.LastName', 'LIKE', "%{$value}%");
                        });
                    } else {
                        $query->where($field, 'LIKE', "%{$value}%");
                    }
                } elseif ($operator === '=') {
                    $query->where($field, '=', $value);
                }
            }

            // Handle filter grup OR (dari searchPanel)
            if (is_array($filter) && isset($filter[1]) && $filter[1] === 'or') {
                $query->where(function ($q) use ($filter) {
                    for ($i = 0; $i < count($filter); $i += 2) {
                        if (is_array($filter[$i]) && count($filter[$i]) === 3) {
                            [$field, $op, $val] = $filter[$i];
                            $op = strtolower($op);

                            if ($op === 'contains') {
                                if ($field === 'GuestName') {
                                    $q->orWhere('tblclient.FirstName', 'LIKE', "%{$val}%")
                                        ->orWhere('tblclient.LastName', 'LIKE', "%{$val}%");
                                } else {
                                    $q->orWhere($field, 'LIKE', "%{$val}%");
                                }
                            } elseif ($op === '=') {
                                $q->orWhere($field, '=', $val);
                            }
                        }
                    }
                });
            }
        }


        if ($request->has('sort')) { //sorting
            $sort = json_decode($request->input('sort'), true);
            if (!empty($sort)) {
                foreach ($sort as $s) {
                    $query->orderBy($s['selector'], $s['desc'] ? 'desc' : 'asc');
                }
            }
        }

        $totalCount = $query->count();
        $data = $query->skip($skip)->take($take)->get();

        return response()->json([
            'data' => $data,
            'totalCount' => $totalCount
        ]);
    }

    public function getAllCompany(Request $request)
    {
        $skip = (int) $request->input('skip', 0);
        $take = (int) $request->input('take', 15);

        $searchValue = $request->input('searchValue', null);
        $searchFields = $request->input('searchFields', null);

        $baseQuery = DB::table('tblcompany')
            ->select(
                'tblcompany.CompID',
                'tblcompany.CompName',
                'tblcompany.CompPhone',
                'tblcompany.CompEmail',
                'tblcompany.creditFacility',
                'tblbusinesssource.busName',
                DB::raw("IF(tblcompany.creditFacility = 0, 'Not Approve', 'Approve') AS strCreditFacility"),
                'tblcompanytype.typeName',
                'tblcompany.isCActive',
                'tblcompany.isBlackList',
                DB::raw("IF(tblcompany.isBlackList = 0, 'NO', 'YES') AS BlackListSTR"),
                'tblcompanygroup.cGroupName',
                'SubQuery_1.SegmentName as segmentName'
            )
            ->join('tblbusinesssource', 'tblbusinesssource.busID', '=', 'tblcompany.busID')
            ->join('tblcompgrade', 'tblcompgrade.gradeID', '=', 'tblcompany.gradeID')
            ->join('tblcompanytype', 'tblcompanytype.cTypeID', '=', 'tblcompany.cTypeID')
            ->leftJoin('tblcompanygroup', 'tblcompanygroup.cGroupID', '=', 'tblcompany.compGroupID')
            ->leftJoin(DB::raw('(SELECT GROUP_CONCAT(tblsegment.segmentName SEPARATOR "\n") AS SegmentName, tblcompanysegment.CompID FROM tblcompanysegment INNER JOIN tblsegment ON tblcompanysegment.segmentID = tblsegment.segmentID GROUP BY tblcompanysegment.CompID) AS SubQuery_1'), 'tblcompany.CompID', '=', 'SubQuery_1.CompID')
            ->where('tblcompany.CompID', '<>', 'xxx');


        // pencarian
        if ($searchValue && $searchFields) {
            $fieldMap = [
                'CompanyName' => 'tblcompany.CompName',
                'CompID' => 'tblcompany.CompID',
                'Address' => 'tblcompany.CompAddress'
            ];
            $fields = array_filter(array_map('trim', explode(',', $searchFields)));
            $baseQuery->where(function ($q) use ($searchValue, $fields, $fieldMap) {
                foreach ($fields as $field) {
                    if (isset($fieldMap[$field])) {
                        $q->orWhere($fieldMap[$field], 'LIKE', "%{$searchValue}%");
                    }
                }
            });
        }

        // sorting
        if ($request->has('sort')) {
            $sort = json_decode($request->input('sort'), true);
            foreach ($sort as $s) {
                $baseQuery->orderBy($s['selector'], $s['desc'] ? 'desc' : 'asc');
            }
        } else {
            $baseQuery->orderBy('tblcompany.CompName'); // default
        }

        // count & data
        $totalCount = (clone $baseQuery)->count(DB::raw('distinct tblcompany.CompID'));

        $data = $baseQuery->skip($skip)->take($take)->get();

        return response()->json([
            'data' => $data,
            'totalCount' => $totalCount
        ]);
    }

    public function getVIPList()
    {
        $query =  DB::table('tblvip')
            ->select(
                'vipID',
                'vipName',
                'isVActive'
            )
            ->get();

        return $query;
    }

    public function getNationality()
    {
        $countries = DB::table("tblcountry")
            ->select('*') // Transform ke format yang diharapkan
            ->orderBy('CountryName', 'ASC')
            ->get();

        return $countries;
    }

    public function getGuestTypeList()
    {
        $query =  DB::table('tblguesttype')
            ->select(
                'guestTypeID',
                'guestTypeName'
            )
            ->get();

        return $query;
    }

    public function getAllCity()
    {
        $query =  DB::table('tblcity')
            ->select(
                'cityID',
                'cUrut',
                'localRegionCode',
                'CityName'
            )
            ->orderBy('CityName', 'ASC')
            ->get();

        return $query;
    }

    public function getAllTypeID()
    {
        $query = DB::table('tbltypeid')->select('*')->get();

        return $query;
    }

    public function getSegmentSubSegmentMap()
    {
        // Gunakan tabel yang sama seperti di kode asli yang berfungsi
        $data = DB::table('tblsegmentsubassign as assign')
            ->join('tblsegment as seg', 'assign.sSegmentID', '=', 'seg.segmentID')
            ->join('tblsegmentsub as sub', 'assign.sSubID', '=', 'sub.subID')
            ->select('seg.segmentID', 'sub.subID', 'sub.subName')
            ->distinct()
            ->get();

        $grouped = $data->groupBy('segmentID')->map(function ($items) {
            return $items->map(function ($item) {
                return [
                    'subID' => $item->subID,
                    'subName' => $item->subName,
                ];
            });
        });


        return response()->json($grouped);
    }

    // Fungsi lainnya tetap sama
    public function getBusinessSourceBySegment(Request $request)
    {
        $segmentID = $request->segment_id;
        $sources = $this->listBusinessSource($segmentID, true);
        return response()->json($sources);
    }

    public function listBusSegment($busID = 0, $segmentID = 0, $isFromPOS = false)
    {
        $query = DB::table('tblbussegment')
            ->select('sBusID as busID', 'sSegmentID as segmentID')
            ->where('sBusID', '<>', 0);

        if ($busID != 0) {
            $query->where('sBusID', $busID);
        }

        if ($segmentID != 0) {
            $query->where('sSegmentID', $segmentID);
        }

        return $query->get();
    }

    public function listBusinessSource($segmentID = 0, $combineRemark = false, $noMice = "", $isFromPOS = false)
    {
        $bsID = 0;

        // 1. Cari bsID kalau ada noMice
        if (!empty($noMice)) {
            $table = $isFromPOS ? "SVDatabaseAdditional.tbltransmice" : "tbltransmice";
            $bsID = DB::table($table)
                ->where('NoMice', $noMice)
                ->value('mBusID') ?? 0;
        }

        // 2. Ambil listBus dari segment
        $dt = $this->listBusSegment(0, $segmentID, $isFromPOS);
        $lstBus = $dt->pluck('busID')->toArray();

        if ($dt->count() == 0 && $bsID == 0) {
            $lstBus[] = 1; // fallback default
        }

        $lstBus[] = $bsID;

        // 3. Table utama
        $table = $isFromPOS ? "SVDatabaseAdditional.tblbusinesssource" : "tblbusinesssource";

        $query = DB::table($table . ' as bs')
            ->select([
                'bs.busID',
                'bs.busCompany',
                'bs.busContact',
                'bs.busEmail',
                'bs.busName',
                'bs.busPhone',
                'bs.planID',
                'bs.planValue',
                $combineRemark
                    ? DB::raw("CONCAT(CONCAT('[', bs.busName, '] '), bs.busRemark) as busRemark")
                    : 'bs.busRemark',
                'bs.busSegmentID',
                'seg.segmentName',
                'bs.COAD',
                'bs.COAC'
            ])
            ->leftJoin('tblsegment as seg', 'seg.segmentID', '=', 'bs.busSegmentID')
            ->where('bs.busName', '<>', 'XXXX');

        // 4. Filter berdasarkan listBus atau segmentID
        if (!empty($lstBus)) {
            $query->whereIn('bs.busID', $lstBus);
        } elseif ($segmentID > 0) {
            $query->where('bs.busSegmentID', $segmentID);
        }

        return $query->get();
    }

    public function getAllSegment()
    {
        return DB::table('tblsegment')
            ->orderByDesc('segmentID')
            ->get();
    }



















    public function getDefaultSetting()
    {
        $defaults = DB::table('tblcomp')->get();

        return response()->json($defaults);
    }

    public function getDefaultTrans()
    {
        $defaults = DB::table('tbldefaulttrans')->get();

        return response()->json($defaults);
    }
}
