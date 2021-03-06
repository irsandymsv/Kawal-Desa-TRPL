<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Notification;
use App\Notifications\notifLaporan;
use App\Notifications\notifPengumuman;
use Carbon\Carbon;
use App\Models\User;
use App\Models\role;
use App\Models\profesi;
use App\Models\province;
use App\Models\regency;
use App\Models\pengumuman;
use App\Models\laporan;
use App\Models\tag;

class perdesController extends Controller
{
    public function index($id)
    {
    	$user = user::findOrFail($id);
    	$peran = role::findOrFail($user->id_role);

        $tagAll = tag::all();
        $thisMonth = Carbon::now()->month;
        $thisYear = Carbon::now()->year;
        $namaBln = array("Januari", "Februari", "Maret", "April", "Mei", "juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember");
        $kriminalitas = tag::where('nama','Kriminalitas')->first();

        // $lap_month = laporan::where('created_at', '>' ,Carbon::now()->subMonth())->get();
        $lap_month = $kriminalitas->laporan()->whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->get();

    	// dd($perdes);
    	return view('\perdes\perdesPage', ['us'=>$user, 'role'=>$peran, 'lap_month'=>$lap_month, "thisMonth"=>$thisMonth, "namaBln"=>$namaBln, "tagAll"=>$tagAll]);
    }

    public function ajaxMap(Request $req)
    {
        $kriminalitas = tag::where('nama','Kriminalitas')->first();
        $kesehatan = tag::where('nama','Kesehatan')->first();
        $pendidikan = tag::where('nama','Pendidikan')->first();
        $thisYear = Carbon::now()->year;

        if (strcasecmp($req->tag, "kriminalitas") == 0) {
            $laporan = $kriminalitas->laporan()->whereMonth('created_at', $req->bulan)->whereYear('created_at', $thisYear)->get();
        }
        elseif (strcasecmp($req->tag, "pendidikan") == 0) {
            $laporan = $pendidikan->laporan()->whereMonth('created_at', $req->bulan)->whereYear('created_at', $thisYear)->get();   
        }
        elseif (strcasecmp($req->tag, "kesehatan") == 0) {
            $laporan = $kesehatan->laporan()->whereMonth('created_at', $req->bulan)->whereYear('created_at', $thisYear)->get(); 
        }
        elseif (strcasecmp($req->tag, "semua") == 0) {
            $laporan = laporan::whereMonth('created_at', $req->bulan)->whereYear('created_at', $thisYear)->get();
        }

        echo $laporan;
    }

    public function profil($id)
    {
    	$user = user::findOrFail($id);
    	// $perdes = user::findOrFail($id);
    	$peran = role::findOrFail($user->id_role);

        $prof = profesi::findOrFail($user->profesi_id);
        $provinsi = province::findOrFail($user->id_prov_asal);
        $kab = regency::findOrFail($user->id_kab_asal);
        $roles = role::findOrFail($user->id_role);

    	// $prof = profesi::all();
     //    $provinsi = province::all();
     //    $kab = regency::all();
     //    $roles = role::all();

        return  view('\perdes\perdesProfil', ['role' => $peran, 'us'=>$user, 'profs' => $prof, 'provinsis' => $provinsi, 'kabs' => $kab, 'roles' => $roles]);
    }

    public function pengumuman($id)
    {
        $stat = "";
        $user = user::findOrFail($id);
        $peran = role::findOrFail($user->id_role);

        $ann = pengumuman::all();
        foreach ($ann as $key) {
            $pengumuman = DB::table('pengumuman')
                        ->join('users', 'pengumuman.id_penulis','=','users.id')
                        // ->where('users.id','=', $key->id_penulis)
                        ->select('pengumuman.*','users.nama')
                        ->orderBy('created_at', 'desc')
                        ->paginate(4);    
        }
        // dd($pengumuman);

        $notifications = Auth::user()->unreadNotifications()->where('type','App\Notifications\notifPengumuman')->get();

        if ($notifications->isNotEmpty()) {
            foreach ($notifications as $notif) {
                $dt = $notif->data;
                $dataId[] = $dt['ann_id'];
                // dd($dt);
            }    
        }else {
            $dataId = 0;
        }

        return view('\perdes\announcePerdes', ['us'=>$user, 'role'=>$peran, 'ann'=>$pengumuman, 'data'=>$dataId, 'status'=>$stat]);
    }

    public function lihatAnn($id, $id2)
    {
        $user = user::findOrFail($id);
        $peran = role::findOrFail($user->id_role);
        // $umumkan = pengumuman::findOrFail($id2);
        $pengumuman = DB::table('pengumuman')
                        ->join('users', 'pengumuman.id_penulis','=','users.id')
                        ->where('pengumuman.id','=',$id2)
                        ->select('pengumuman.*', 'users.nama')
                        ->first();

        $pengubah = DB::table('pengumuman')
                        ->join('users', 'pengumuman.id_pengubah','=','users.id')
                        ->where('pengumuman.id','=',$id2)
                        ->select('users.nama')
                        ->first();

        if (is_null($pengubah)) {
            $ubah = $pengubah;
        } else {
            $ubah = $pengubah->nama;
        }
        // dd($ubah);

        $notifications = Auth::user()->unreadNotifications()->where('type','App\Notifications\notifPengumuman')->get();

        foreach ($notifications as $notif) {
            $dt = $notif->data;
            $dataId = $dt['ann_id'];
            if ($dataId == $id2) {
                $notif->markAsRead();
                break;
                // dd($notif);
            }
        }

        return view('\perdes\LihatAnnounce', ['us'=>$user, 'role'=>$peran, 'ann'=>$pengumuman, 'ubah'=>$ubah]);
    }

    public function baruAnn($id)
    {
        $user = user::findOrFail($id);
        $peran = role::findOrFail($user->id_role);
        
        return view('\perdes\newAnnounce', ['us'=>$user, 'role'=>$peran]);
    }

    public function createAnn(Request $req, $id)
    {
        $this->validate($req,[
            'judul' => 'required|string|min:4|unique:pengumuman',
            'isi' => 'required|string|min:12',
            'status' => 'required',
        ]);

        // pengumuman::create([
        //     'judul' => $req->judul,
        //     'isi' => $req->isi,
        //     'id_penulis' => $id
            
        // ]);

        // $date = Carbon::now();
        // $carbon = Carbon::createFromFormat('Y-m-d H:i:s', $date, 'UTC'); 
        // $carbon->tz = 'Asia/Jakarta';

        $ann = new pengumuman;
        $ann->judul = $req->judul;
        $ann->isi = $req->isi;
        $ann->status = $req->status;
        $ann->id_penulis = $id;
        $ann->save();

        $id2 = $ann->id;
        return redirect('/perdes/'.$id.'/pengumuman/'.$id2);
    }

    public function editAnn($id, $id2)
    {
        $user = user::findOrFail($id);
        $peran = role::findOrFail($user->id_role);
        $ann = pengumuman::findOrFail($id2);

        return view('\perdes\editAnnounce', ['us'=>$user, 'role'=>$peran, 'ann'=>$ann]);
    }

    public function updateAnn(Request $req, $id, $id2)
    {
        $this->validate($req,[
            'judul' => 'required|string|min:4',
            'isi' => 'required|string|min:12',
            // 'status' => 'required',
        ]);

        $ann = pengumuman::findOrFail($id2);
        $ann->judul = $req->judul;
        $ann->isi = $req->isi;
        // $ann->status = $req->status;
        $ann->id_pengubah = $id;
        $ann->save();

        return redirect('/perdes/'.$id.'/pengumuman/'.$id2);

    }

    public function statusAnn(Request $req, $id, $id2)
    {
        $this->validate($req,[
            'status' => 'required'
        ]);
        
        $ann = pengumuman::findOrFail($id2);

        if ( ($req->status == "dipublikasi") && ($ann->published_at == "") ) {
            $publish = Carbon::now();
            $ann->status = $req->status;
            $ann->published_at = $publish;
            $ann->id_pengubah = $id;
            $ann->save();

            $users = User::where('id','!=',$id)->get();
            Notification::send($users, new notifPengumuman($ann));
        }
        elseif ( ($req->status == "dipublikasi") && ($ann->published_at != "") ) {
            $publish = Carbon::now();
            $ann->status = $req->status;
            $ann->published_at = $publish;
            $ann->id_pengubah = $id;
            $ann->save();
        }
        elseif ($req->status == "disimpan") {
            $ann->status = $req->status;
            $ann->id_pengubah = $id;
            $ann->save();

            // $notifikasi = Notification:: where("data['ann_id']",$id2)->get();
            $notifikasi = DB::table('notifications')->where('type','App\Notifications\notifPengumuman')->get();
            // dd($notifikasi);
            foreach ($notifikasi as $key) {
                $isiData = $key->data;
                $hasil = json_decode($isiData);
                $annId = $hasil->ann_id;
                // dd($annId);

                if ($annId == $id2) {
                    // dd($key->id);
                    DB::table('notifications')->where('id', $key->id)->delete();
                }
            }
        }
        
        return redirect('/perdes/'.$id.'/pengumuman/'.$id2);

    }

    public function laporan($id)
    {
        $user = user::findOrFail($id);
        $peran = role::findOrFail($user->id_role);

        $laporan = DB::table('laporan')
                        ->join('users', 'laporan.id_penulis','=','users.id')
                        ->select('laporan.*','users.nama')
                        ->orderBy('created_at', 'desc')
                        ->get();

        $notifications = Auth::user()->unreadNotifications()->where('type','App\Notifications\notifLaporan')->get();

        if ($notifications->isNotEmpty()) {
            foreach ($notifications as $notif) {
                $dt = $notif->data;
                $dataId[] = $dt['lap_id'];
                // dd($dt);
            }    
        }else {
            $dataId = 0;
        }

        return view('\perdes\perdesLaporan', ['us'=>$user, 'role'=>$peran, 'laporan'=>$laporan, 'data'=>$dataId]);
    }

    public function laporanBaru($id)
    {
        $user = user::findOrFail($id);
        $peran = role::findOrFail($user->id_role);

        $prof = profesi::all();
        $tag = tag::all();
        return view('\perdes\perdesNewLaporan', ['us'=>$user, 'role'=>$peran, 'prof'=>$prof, 'tag'=>$tag]);                
    }

    public function createLaporan(Request $req, $id)
    {
        $this->validate($req,[
            'judul' => 'required|string|min:4|unique:laporan',
            'isi' => 'required|string|min:12',
            'tag' => 'required|min:1',
            'prof' => 'required|min:1',
            'koordinat' => 'required',
            'status' => 'required',
        ]);

        // dd($req->koordinat);
        $path="";
        $lap = new laporan;
        if ($req->hasFile('img_file')) {
            $lap->judul = $req->judul;
            $lap->isi = $req->isi;
            $lap->koordinat = $req->koordinat;
            $lap->status = $req->status;
            $lap->id_penulis = $id;
            $lap->save();

            $fileName = $lap->id."_".date('d-M-Y').".".$req->file('img_file')->getClientOriginalExtension();
            $path = $req->file('img_file')->storeAs('laporan-image', $fileName);
            $lap->image_url = $path;
            $lap->save();
        }
        else{
            $lap->judul = $req->judul;
            $lap->isi = $req->isi;
            $lap->koordinat = $req->koordinat; 
            $lap->status = $req->status;
            $lap->id_penulis = $id;
            $lap->save();
        }
        
        if ($req->tag != null) {
            foreach ($req->tag as $key) {
                $lap->tags()->attach($key);
            }
        }

        if ($req->prof != null) {
            foreach ($req->prof as $key) {
                $lap->profesi()->attach($key);
            }
        }

        $users = User::where('id', '!=', $id)->get();
        Notification::send($users, new notifLaporan($lap));

        return redirect('/perdes/'.$id.'/laporan');
        
    }

    public function lihatLaporan($id, $id2)
    {
        $user = user::findOrFail($id);
        $peran = role::findOrFail($user->id_role);

        $laporan = laporan::findOrFail($id2);
        $tag = $laporan->tags()->get();
        $prof = $laporan->profesi()->get();
        $penulis = $laporan->user()->first();
        $pengubah = User::where('id',$laporan->id_pengubah)->first();

        $notifications = Auth::user()->unreadNotifications()->where('type','App\Notifications\notifLaporan')->get();

        foreach ($notifications as $notif) {
            $dt = $notif->data;
            $dataId = $dt['lap_id'];
            if ($dataId == $id2) {
                $notif->markAsRead();
                break;
                // dd($dt);
            }
        }

        return view('/perdes/perdesLihatLaporan', ['us'=>$user, 'role'=>$peran, 'laporan'=>$laporan, 'penulis'=>$penulis, 'pengubah'=>$pengubah, 'prof'=>$prof, 'tag'=>$tag]);
    }

    public function editLaporan($id, $id2)
    {
        $user = user::findOrFail($id);
        $peran = role::findOrFail($user->id_role);

        $laporan = laporan::findOrFail($id2);
        $tag = $laporan->tags()->get();
        $prof = $laporan->profesi()->get();

        $profAll = profesi::all();
        $tagAll = tag::all();

        return view('/perdes/perdesEditLaporan', ['us'=>$user, 'role'=>$peran, 'laporan'=>$laporan, 'prof'=>$prof, 'tag'=>$tag, 'profAll' => $profAll, 'tagAll'=>$tagAll]);
    }

    public function updateLaporan(Request $req, $id, $id2)
    {
        $this->validate($req,[
            'judul' => 'required|string|min:4',
            'isi' => 'required|string|min:12',
            'tag' => 'required|min:1',
            'prof' => 'required|min:1',
            'koordinat' => 'required',
        ]);
        
        $lap = laporan::findOrFail($id2);
        // dd($req->del);

        if ($req->hasFile('img_file')) {
            Storage::delete("$lap->image_url");
            $fileName = $lap->id."_".date('d-M-Y').".".$req->file('img_file')->getClientOriginalExtension();
            $path = $req->file('img_file')->storeAs('laporan-image', $fileName);
            
            $lap->judul = $req->judul;
            $lap->isi = $req->isi;
            $lap->koordinat = $req->koordinat;
            $lap->image_url = $path;
            $lap->id_pengubah = $id;
            $lap->save();
        }
        else{
            if ($req->del == "delete") {
                Storage::delete("$lap->image_url");
                $lap->judul = $req->judul;
                $lap->isi = $req->isi;
                $lap->koordinat = $req->koordinat;
                $lap->image_url = $req->file('img_file');
                $lap->id_pengubah = $id;
                $lap->save();
            }else{
                $lap->judul = $req->judul;
                $lap->isi = $req->isi;
                $lap->koordinat = $req->koordinat;
                $lap->id_pengubah = $id;
                $lap->save();
            }
            
        }

        if ($req->tag != null) {
            $lap->tags()->sync($req->tag);
        } else{
            $lap->tags()->detach();
        }

        if ($req->prof != null) {
            $lap->profesi()->sync($req->prof);
        } else{
            $lap->profesi()->detach();
        }

        return redirect('/perdes/'.$id.'/laporan/'.$id2);
    }

    public function deleteLaporan($id, $id2)
    {
        $lap = laporan::findOrFail($id2);
        $notifikasi = DB::table('notifications')
                        ->where('type','App\Notifications\notifLaporan')
                        ->get();
        // dd($notifikasi);

        foreach ($notifikasi as $key) {
            $isiData = $key->data;
            $hasil = json_decode($isiData);
            $lapId = $hasil->lap_id;
            
            if ($lapId == $id2) {
                DB::table('notifications')->where('id', $key->id)->delete();
            }
        }
        
        Storage::delete("$lap->image_url");
        $lap->delete();

        return redirect ('/perdes/'.$id.'/laporan');
    }

    public function statusLaporan(Request $req, $id, $id2)
    {
        // dd($req->status);
        $this->validate($req,[
            'status' => 'required',
        ]);

        $lap = laporan::findOrFail($id2);
        if ($req->status == "Sudah ditangani") {
            $lap->status = $req->status;
            $lap->id_solver = $id;
            $lap->solved_at = Carbon::now();
            $lap->save();

        }else if($req->status == "Belum ditangani"){
            $lap->status = $req->status;
            $lap->id_solver = null;
            $lap->solved_at = null;
            $lap->save();
        }
        

        return redirect('/perdes/'.$id.'/laporan/');
    }

    

}
