define("navbar",["jquery","bootstrap"],function(e){return{init:function(){e(".navbar .online a").each(function(){function s(){t||(t=e('<div class="modal fade">                                    <div class="modal-dialog">                                        <div class="modal-content">                                            <div class="modal-header">                                                <button type="button" data-dismiss="modal" class="close">×</button>                                                <h3 class="modal-title">Online</h3>                                            </div>                                            <div class="modal-body"></div>                                            <div class="modal-footer">                                                <button class="btn btn-primary">Обновить</a>                                                <button data-dismiss="modal" class="btn btn-default">Закрыть</button>                                            </div>                                        </div>                                    </div>                                </div>'),n=t.find(".modal-body"),r=t.find(".btn-primary").on("click",function(e){e.preventDefault(),s()})),n.empty(),t.modal(),r.button("loading"),e.get(i,{},function(e){n.html(e),r.button("reset")})}var t=null,n=null,r=null,i=e(this).attr("href");e(this).on("click",function(e){e.preventDefault(),s()})})}}});