@extends('enquiries.layout')
@section('title', $title)
@section('meta_description', $intro)
@section('enquiry_heading', $title)
@section('enquiry_lead', $intro)
@section('enquiry_intro', 'Complete the form below and our team will contact you about the next steps.')
@section('enquiry_form')
    @include('partials.public-enquiry-form', ['formId' => 'page-enquiry', 'formTitle' => $title.' form'])
@endsection
