@extends('layouts.master')

@section('title')
    {{ __('DingTalk Binding Management') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('DingTalk Binding Management') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card search-container">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title"></h4>

                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('dingtalk.bindings.list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true"
                            data-fixed-columns="false" data-trim-on-search="false" data-mobile-responsive="true"
                            data-sort-name="id" data-sort-order="desc" data-maintain-selected="true"
                            data-escape="true">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false" data-align="center">{{ __('ID') }}</th>
                                    <th scope="col" data-field="school_name" data-sortable="true" data-align="center">{{ __('School') }}</th>
                                    <th scope="col" data-field="school_code" data-sortable="true" data-align="center">{{ __('School Code') }}</th>
                                    <th scope="col" data-field="user_name" data-sortable="true" data-align="center">{{ __('User Name') }}</th>
                                    <th scope="col" data-field="user_email" data-sortable="true" data-align="center">{{ __('Email') }}</th>
                                    <th scope="col" data-field="dingtalk_open_id_masked" data-sortable="false" data-align="center">{{ __('DingTalk Open ID') }}</th>
                                    <th scope="col" data-field="dingtalk_union_id_masked" data-sortable="false" data-align="center">{{ __('DingTalk Union ID') }}</th>
                                    <th scope="col" data-field="dingtalk_nick" data-sortable="false" data-align="center">{{ __('DingTalk Nickname') }}</th>
                                    <th scope="col" data-field="last_login_at" data-sortable="true" data-align="center">{{ __('Last Login') }}</th>
                                    <th scope="col" data-field="created_at" data-sortable="true" data-align="center">{{ __('Bound At') }}</th>
                                    <th scope="col" data-field="operate" data-sortable="false" data-align="center" data-escape="false">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        // 解绑按钮事件
        $(document).on('click', '.unbind-dingtalk-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var bindingId = $btn.data('id');

            Swal.fire({
                title: window.trans['Are you sure'] || 'Are you sure?',
                text: 'This will remove the DingTalk binding. The user and school will NOT be deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: window.trans['yes_delete'] || 'Yes, unbind it!',
                cancelButtonText: window.trans['Cancel'] || 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    var url = baseUrl + '/dingtalk/bindings/' + bindingId;
                    var data = {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        _method: 'DELETE'
                    };

                    function successCallback(response) {
                        $('#table_list').bootstrapTable('refresh');
                        showSuccessToast(response.message || 'Binding removed.');
                    }

                    function errorCallback(response) {
                        showErrorToast(response.message || 'Failed to unbind.');
                    }

                    ajaxRequest('DELETE', url, null, null, successCallback, errorCallback);
                }
            });
        });
    </script>
@endsection
