@extends('errors.layout')

@section('code', '403')
@section('heading', 'Access denied')
@section('message', (isset($exception) && filled($exception->getMessage())) ? $exception->getMessage() : 'You do not have permission to access this page.')
