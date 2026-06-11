@extends('layouts.master')

@section('title')
    {{ __('Finance Categories') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('Finance Categories') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('Create Finance Category') }}
                        </h4>
                        <form class="pt-3" id="create-form" action="{{ route('finance-category.store') }}" method="POST" novalidate="novalidate">
                            @csrf
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-3">
                                    <label>{{ __('Type') }} <span class="text-danger">*</span></label>
                                    <select name="type" id="type" class="form-control" required>
                                        <option value="">{{ __('Select Type') }}</option>
                                        <option value="income">{{ __('Income') }}</option>
                                        <option value="expense">{{ __('Expense') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-sm-12 col-md-3">
                                    <label>{{ __('Category Code') }} <span class="text-danger">*</span></label>
                                    <input name="category_code" id="category_code" type="text" placeholder="{{ __('e.g. TUITION_FEE') }}" class="form-control" required maxlength="100" />
                                </div>

                                <div class="form-group col-sm-12 col-md-3">
                                    <label>{{ __('Name') }} <span class="text-danger">*</span></label>
                                    <input name="name" id="name" type="text" placeholder="{{ __('Name') }}" class="form-control" required maxlength="255" />
                                </div>

                                <div class="form-group col-sm-12 col-md-3">
                                    <label>{{ __('Local Name') }}</label>
                                    <input name="local_name" id="local_name" type="text" placeholder="{{ __('Local Name') }}" class="form-control" maxlength="255" />
                                </div>

                                <div class="form-group col-sm-12 col-md-8">
                                    <label>{{ __('Description') }}</label>
                                    <textarea name="description" id="description" placeholder="{{ __('Description') }}" class="form-control" rows="2"></textarea>
                                </div>

                                <div class="form-group col-sm-12 col-md-2">
                                    <label>{{ __('Sort Order') }}</label>
                                    <input name="sort_order" id="sort_order" type="number" min="0" value="0" class="form-control" />
                                </div>
                            </div>
                            <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit" value="{{ __('submit') }}">
                            <input class="btn btn-secondary float-right" type="reset" value="{{ __('reset') }}">
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('List Finance Categories') }}</h4>

                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('finance-category.list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100]"
                            data-search="true" data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                            data-trim-on-search="false" data-mobile-responsive="true"
                            data-sort-name="sort_order" data-sort-order="asc"
                            data-maintain-selected="true" data-export-data-type='all'
                            data-show-export="true" data-escape="true">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                    <th scope="col" data-field="type_badge" data-sortable="true" data-escape="false">{{ __('Type') }}</th>
                                    <th scope="col" data-field="category_code" data-sortable="true">{{ __('Code') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                    <th scope="col" data-field="local_name" data-sortable="true">{{ __('Local Name') }}</th>
                                    <th scope="col" data-field="description" data-sortable="false">{{ __('Description') }}</th>
                                    <th scope="col" data-field="status_badge" data-sortable="true" data-escape="false">{{ __('Status') }}</th>
                                    <th scope="col" data-field="sort_order" data-sortable="true">{{ __('Sort Order') }}</th>
                                    <th scope="col" data-field="operate" data-events="financeCategoryEvents" data-escape="false">{{ __('action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Edit Modal --}}
            <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editModalLabel">{{ __('Edit Finance Category') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form class="pt-3 edit-form" id="" action="{{ url('finance-category') }}" novalidate="novalidate">
                            @csrf
                            <div class="modal-body">
                                <input type="hidden" name="id" id="edit_id" value="" />
                                <div class="row">
                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('Type') }} <span class="text-danger">*</span></label>
                                        <select name="type" id="edit_type" class="form-control" required>
                                            <option value="income">{{ __('Income') }}</option>
                                            <option value="expense">{{ __('Expense') }}</option>
                                        </select>
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('Category Code') }} <span class="text-danger">*</span></label>
                                        <input name="category_code" id="edit_category_code" type="text" class="form-control" required maxlength="100" />
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('Name') }} <span class="text-danger">*</span></label>
                                        <input name="name" id="edit_name" type="text" class="form-control" required maxlength="255" />
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('Local Name') }}</label>
                                        <input name="local_name" id="edit_local_name" type="text" class="form-control" maxlength="255" />
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('Sort Order') }}</label>
                                        <input name="sort_order" id="edit_sort_order" type="number" min="0" class="form-control" />
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('Status') }}</label>
                                        <select name="is_active" id="edit_is_active" class="form-control">
                                            <option value="1">{{ __('Active') }}</option>
                                            <option value="0">{{ __('Inactive') }}</option>
                                        </select>
                                    </div>

                                    <div class="form-group col-sm-12 col-md-12">
                                        <label>{{ __('Description') }}</label>
                                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('close') }}</button>
                                    <input class="btn btn-theme" type="submit" value="{{ __('submit') }}" />
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
