<?php
namespace App\Flows\Usuarios\Http;
use App\Flows\Usuarios\Models\UsuarioSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class UsuariosController { public function index(Request $request): JsonResponse { $q=UsuarioSistema::query(); if($request->filled('tipo_usuario'))$q->where('tipo_usuario',$request->string('tipo_usuario')); if($request->filled('estado'))$q->where('estado',$request->string('estado')); if($request->filled('buscar')){ $s=$request->string('buscar');$q->where(fn($x)=>$x->where('nombres','like',"%$s%")->orWhere('apellidos','like',"%$s%")->orWhere('usuario','like',"%$s%")); } return response()->json($q->latest()->paginate($request->integer('por_pagina',20))); } public function store(RegistrarUsuarioRequest $request): JsonResponse { $d=$request->validated();$d['password']=Hash::make($d['password']);$d['estado']=$d['estado']??'ACTIVO'; return response()->json(['data'=>UsuarioSistema::create($d)],201); } }
