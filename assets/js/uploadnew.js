new Vue({
    el: '#app',
    data: {
        uploadTitle: '选择文件/Ctrl+V粘贴/拖拽到此上传',
        background: '#fff',
        showtype: 0,
        isBlock: false,
        alert: {
            type: 'success',
            msg: ''
        },
        beginTime: 0,
        loaded_size: 0,
        progress: 0,
        progress_tip: '',
        filename: '',
        uploadspeed: '',
        totalProgress: 0,
        batchTotal: 0,
        batchIndex: 0,
        batchResults: [],
        batchErrors: [],
        batchQueue: [],
        queueSeed: 0,
        uploadConcurrency: 3,
        chunkConcurrency: 3,
        uploadCountLimit: 0,
        uploadCountRemaining: -1,
        currentFile: null,
        input: {
            csrf_token:'',
            show: true,
            ispwd: false,
            pwd: '',
            hash: '',
            name: '',
            size: 0
        },
    },
    computed: {
        alertIconClass(){
            if(this.alert.type === 'danger') return 'fa-times';
            if(this.alert.type === 'warning') return 'fa-exclamation';
            return 'fa-check';
        }
    },
    mounted() {
        $(".colorful_loading_frame").hide();
        this.input.csrf_token = $("#csrf_token").val();
        this.uploadCountLimit = typeof upload_count_limit !== 'undefined' ? parseInt(upload_count_limit) : 0;
        this.uploadCountRemaining = typeof upload_count_remaining !== 'undefined' ? parseInt(upload_count_remaining) : -1;

        var that = this;
        var fileInput = $("#fileInput");
        var elemetnNode="";
        fileInput.on("dragenter",function(e){
            elemetnNode=e.originalEvent.target;
            that.uploadTitle = '释放鼠标立即上传';
            that.background = '#ccc';
        });
        fileInput.on("dragleave",function(e){
            if(elemetnNode===e.originalEvent.target){
                that.uploadTitle = '选择文件/Ctrl+V粘贴/拖拽到此上传';
                that.background = '#fff';
            }
        });
        fileInput.on('dragover', false).on("drop",function(e){
            that.uploadTitle = '选择文件/Ctrl+V粘贴/拖拽到此上传';
            that.background = '#fff';
            var fs = e.originalEvent.dataTransfer.files;
            if(fs.length>0){
                that.uploadFiles(fs);
            }
            return false;
        });

        document.addEventListener('paste', function(e) {
            var items = ((e.clipboardData || window.clipboardData).items) || [];
            var files = [];

            if (items && items.length) {
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('text/') === -1) {
                        var file = items[i].getAsFile();
                        if(file) files.push(file);
                    }
                }
            }

            if (files.length === 0) {
                return;
            }
            that.uploadFiles(files);
        });
    },
    methods: {
        show_msg(msg, type, keepBlock){
            type = type || 'success';
            this.alert.type = type;
            this.alert.msg = msg;
            this.showtype = 2;
            if(!keepBlock) this.isBlock=false;
            $("#file").val('');
        },
        clickUpload(){
            if(this.isBlock) return;
            $("#file").trigger("click");
        },
        async selectFile(e){
            var total = e.target.files.length;
            if(total == 0) return;
            await this.uploadFiles(e.target.files);
        },
        async uploadFile(file){
            return this.uploadFiles([file]);
        },
        async uploadFiles(fileList){
            if(this.isBlock) return;
            if(typeof forbid!=='undefined' && forbid){
                layer.alert('登录后才能上传文件！',{icon:0},function(){window.location.href='./login.php'});
                return;
            }

            var files = Array.prototype.slice.call(fileList || []);
            if(files.length === 0) return;

            this.prepareBatchQueue(files);
            var allowedFiles = files;
            if(this.uploadCountLimit > 0){
                var remaining = Math.max(0, this.uploadCountRemaining);
                allowedFiles = files.slice(0, remaining);
                for(var limitIndex = remaining; limitIndex < files.length; limitIndex++){
                    this.updateQueueItem(files[limitIndex], {
                        status: 'limited',
                        msg: '超过今日剩余上传数量'
                    });
                }
                if(allowedFiles.length === 0){
                    this.show_msg('今日上传数量已达到限制，无法继续上传。', 'warning');
                    return;
                }
            }

            this.isBlock = true;
            this.batchTotal = allowedFiles.length;
            this.batchIndex = 0;
            this.batchResults = [];
            this.batchErrors = [];

            if(allowedFiles.length === 1 && files.length === 1){
                try {
                    var result = await this.uploadSingle(allowedFiles[0], 1, 1);
                    this.batchResults.push({file: allowedFiles[0], result: result});
                } catch (e) {
                    this.show_msg(this.escapeHtml(e.message || e || '上传失败'), 'danger');
                    return;
                }
                this.isBlock = false;
                this.showBatchResult();
                return;
            }

            await this.runConcurrentUploads(allowedFiles);

            this.isBlock = false;
            this.showBatchResult();
        },
        async runConcurrentUploads(files){
            var that = this;
            var nextIndex = 0;
            var workerCount = Math.min(this.uploadConcurrency || 3, files.length);
            var workers = [];
            this.progress_tip = '并发上传中，最多同时上传 '+workerCount+' 个文件';

            async function worker(){
                while(nextIndex < files.length){
                    var currentIndex = nextIndex++;
                    var file = files[currentIndex];
                    try {
                        var itemResult = await that.uploadSingle(file, currentIndex + 1, files.length);
                        that.batchResults.push({file: file, result: itemResult});
                    } catch (e) {
                        that.updateQueueItem(file, {
                            status: 'error',
                            msg: e.message || e || '上传失败'
                        });
                        that.batchErrors.push({file: file, msg: e.message || e || '上传失败'});
                    }
                }
            }

            for(var i = 0; i < workerCount; i++){
                workers.push(worker());
            }
            await Promise.all(workers);
        },
        prepareBatchQueue(files){
            this.batchQueue = files.map(file => {
                this.queueSeed++;
                return {
                    id: this.queueSeed,
                    file: file,
                    name: file.name,
                    sizeText: this.size_format(file.size),
                    status: 'waiting',
                    progress: 0,
                    speed: '',
                    msg: '',
                    url: '',
                    downloadUrl: '',
                    viewUrl: ''
                };
            });
            this.totalProgress = 0;
            this.showtype = 1;
        },
        getQueueItem(file){
            for(var i = 0; i < this.batchQueue.length; i++){
                if(this.batchQueue[i].file === file) return this.batchQueue[i];
            }
            return null;
        },
        updateQueueItem(file, data){
            var item = this.getQueueItem(file);
            if(!item) return;
            for(var key in data){
                item[key] = data[key];
            }
            this.updateTotalProgress();
        },
        updateTotalProgress(){
            if(this.batchQueue.length === 0){
                this.totalProgress = this.progress;
                return;
            }
            var sum = 0;
            for(var i = 0; i < this.batchQueue.length; i++){
                var item = this.batchQueue[i];
                if(item.status === 'success' || item.status === 'limited'){
                    sum += 100;
                }else if(item.status === 'error'){
                    sum += item.progress || 0;
                }else{
                    sum += item.progress || 0;
                }
            }
            this.totalProgress = Math.min(100, Math.round(sum / this.batchQueue.length));
        },
        queueStatusText(status){
            var map = {
                waiting: '等待',
                reading: '读取中',
                uploading: '上传中',
                confirming: '等待确认',
                saving: '保存中',
                success: '成功',
                error: '失败',
                limited: '超出限制'
            };
            return map[status] || status;
        },
        queueStatusClass(status){
            var map = {
                waiting: 'label-default',
                reading: 'label-info',
                uploading: 'label-primary',
                confirming: 'label-info',
                saving: 'label-warning',
                success: 'label-success',
                error: 'label-danger',
                limited: 'label-warning'
            };
            return map[status] || 'label-default';
        },
        async uploadSingle(file, index, total){
            var that = this;
            var ctx = {
                file: file,
                index: index,
                total: total,
                hash: '',
                name: file.name,
                size: file.size,
                loadedSize: 0,
                chunkLoaded: {}
            };
            if(upload_max_filesize != '' && parseInt(upload_max_filesize) > 0){
                if(file.size > parseInt(upload_max_filesize) * 1024 * 1024){
                    this.updateQueueItem(file, {
                        status: 'error',
                        msg: '超过上传大小限制' + upload_max_filesize + 'MB'
                    });
                    throw new Error('超过上传大小限制' + upload_max_filesize + 'MB');
                }
            }

            this.batchIndex = index;
            this.currentFile = file;
            this.input.name = file.name;
            this.input.size = file.size;
            this.progress = 0;
            this.totalProgress = total > 1 ? this.totalProgress : 0;
            this.loaded_size = 0;
            this.uploadspeed = '';
            this.progress_tip = total > 1 ? '正在上传 '+index+'/'+total : '';
            this.showtype = 1;
            this.beginTime = new Date().getTime();
            this.updateQueueItem(file, {
                status: 'reading',
                progress: 0,
                msg: ''
            });

            var result = {};
            await this.getFileHash(file, ctx).then(res =>{
                ctx.hash = res;
                that.input.hash = res;
            });
            await this.preUpload(ctx).then(res =>{
                result = res;
            }, res => {
                throw new Error(res);
            });

            this.filename = (total > 1 ? '['+index+'/'+total+'] ' : '') + file.name + ' （'+this.size_format(file.size)+'）';
            if(result.code == 1){
                var existsLinks = this.buildFileLinks(result);
                this.progress = 100;
                this.totalProgress = total > 1 ? this.totalProgress : 100;
                this.logUploadTiming(result, file);
                this.updateQueueItem(file, {
                    status: 'success',
                    progress: 100,
                    msg: result.exists == 1 ? '秒传成功' : '',
                    url: existsLinks.viewUrl,
                    downloadUrl: existsLinks.downloadUrl,
                    viewUrl: existsLinks.viewUrl
                });
                if(this.uploadCountLimit > 0){
                    this.uploadCountRemaining = Math.max(0, this.uploadCountRemaining - 1);
                }
                return result;
            }
            this.updateQueueItem(file, {
                status: 'uploading',
                progress: 0,
                msg: ''
            });

            if(result.third){
                await this.uploadThird(result.url, result.post, file, ctx).then(res =>{
                }, res => {
                    throw new Error(res);
                });

                await this.completeUpload(ctx).then(res =>{
                    result = res;
                }, res => {
                    throw new Error(res);
                });

            }else{
                var chunkSize = result.chunksize;
                var chunks = result.chunks;
                ctx.chunks = chunks;
                ctx.chunkSize = chunkSize;
                ctx.loadedSize = 0;
                ctx.chunkLoaded = {};
                if(chunks == 1){
                    await this.uploadPart(file, 1, ctx).then(res =>{
                        result = res;
                    }, res => {
                        throw new Error(res);
                    });
                }else{
                    result = await this.uploadChunksConcurrent(file, chunkSize, chunks, ctx);
                }
            }
            var successLinks = this.buildFileLinks(result);
            this.updateQueueItem(file, {
                status: 'success',
                progress: 100,
                msg: result.exists == 1 ? '本站已存在' : '',
                url: successLinks.viewUrl,
                downloadUrl: successLinks.downloadUrl,
                viewUrl: successLinks.viewUrl
            });
            this.logUploadTiming(result, file);
            if(this.uploadCountLimit > 0){
                this.uploadCountRemaining = Math.max(0, this.uploadCountRemaining - 1);
            }
            if(total === 1) this.totalProgress = 100;
            return result;
        },
        async uploadChunksConcurrent(file, chunkSize, chunks, ctx){
            var that = this;
            var blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice;
            var nextChunk = 1;
            var finalResult = null;
            var workerCount = Math.min(this.chunkConcurrency || 3, chunks);
            var workers = [];
            this.updateQueueItem(file, {
                status: 'uploading',
                msg: '分片并发上传 '+workerCount+'/'+chunks
            });

            async function worker(){
                while(nextChunk <= chunks){
                    var chunk = nextChunk++;
                    var start = (chunk - 1) * chunkSize;
                    var end = start + chunkSize > file.size ? file.size : start + chunkSize;
                    var blob = blobSlice.call(file, start, end);
                    var res = await that.uploadPart(blob, chunk, ctx);
                    if(res && res.code == 1){
                        finalResult = res;
                    }
                }
            }

            for(var i = 0; i < workerCount; i++){
                workers.push(worker());
            }
            await Promise.all(workers);
            if(!finalResult){
                throw new Error('分片上传完成，但服务器未返回保存结果，请重试');
            }
            return finalResult;
        },
        updateCsrfToken(data){
            if(data && data.csrf_token){
                this.input.csrf_token = data.csrf_token;
                $("#csrf_token").val(data.csrf_token);
            }
        },
        logUploadTiming(result, file){
        },
        nowMs(){
            return (window.performance && performance.now) ? performance.now() : new Date().getTime();
        },
        startFrontendTiming(label, file){
            return {
                label: label,
                fileName: file && file.name ? file.name : (this.currentFile && this.currentFile.name ? this.currentFile.name : 'unknown'),
                startAt: this.nowMs(),
                uploadDoneAt: null
            };
        },
        markFrontendUploadDone(timing){
            if(timing && timing.uploadDoneAt === null){
                timing.uploadDoneAt = this.nowMs();
            }
        },
        logFrontendTiming(timing, extraRows){
        },
        getSiteBaseUrl(){
            var path = window.location.pathname || '/';
            var dir = path.substring(0, path.lastIndexOf('/') + 1);
            return window.location.origin + dir;
        },
        buildFileLinks(result){
            var token = result && (result.token || result.hash) ? (result.token || result.hash) : '';
            var type = result && result.type ? result.type : 'file';
            var pwd = this.input.ispwd && this.input.pwd ? this.input.pwd : '';
            var base = this.getSiteBaseUrl();
            return {
                downloadUrl: base + 'down.php/' + encodeURIComponent(token) + '.' + encodeURIComponent(type) + (pwd ? '&' + encodeURIComponent(pwd) : ''),
                viewUrl: base + 'file.php?hash=' + encodeURIComponent(token) + (pwd ? '&pwd=' + encodeURIComponent(pwd) : '')
            };
        },
        successfulQueueItems(){
            return this.batchQueue.filter(function(item){
                return item.status === 'success' && item.downloadUrl && item.viewUrl;
            });
        },
        copyAllDownloadLinks(){
            var links = this.successfulQueueItems().map(function(item){ return item.downloadUrl; });
            this.copyText(links.join('\n'));
        },
        copyAllViewLinks(){
            var links = this.successfulQueueItems().map(function(item){ return item.viewUrl; });
            this.copyText(links.join('\n'));
        },
        copyText(text){
            if(!text) return;
            var that = this;
            if(navigator.clipboard && window.isSecureContext){
                navigator.clipboard.writeText(text).then(function(){
                    layer.msg('复制成功', {icon: 1});
                }).catch(function(){
                    that.fallbackCopyText(text);
                });
                return;
            }
            this.fallbackCopyText(text);
        },
        fallbackCopyText(text){
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                layer.msg('复制成功', {icon: 1});
            } catch (e) {
                layer.msg('复制失败，请手动复制', {icon: 2});
            }
            document.body.removeChild(textarea);
        },
        async preUpload(ctx){
            var postData = {
                csrf_token: this.input.csrf_token,
                name: ctx ? ctx.name : this.input.name,
                hash: ctx ? ctx.hash : this.input.hash,
                size: ctx ? ctx.size : this.input.size,
                show: this.input.show?'1':'0',
                ispwd: this.input.ispwd?'1':'0',
                pwd: this.input.pwd,
            };
            var that = this;
            var timing = this.startFrontendTiming('pre_upload', ctx ? ctx.file : this.currentFile);
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'POST',
                    url: 'ajax.php?act=pre_upload',
                    data: postData,
                    dataType: 'json',
                    success: function(data) {
                        that.logFrontendTiming(timing);
                        that.updateCsrfToken(data);
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error:function(){
                        that.logFrontendTiming(timing, [{step: 'result', value: 'error'}]);
                        layer.msg('服务器错误');
                        reject('上传失败，请稍后再试或联系站长');
                    }
                });
            })
        },
        async completeUpload(ctx){
            var that = this;
            var timing = this.startFrontendTiming('complete_upload', ctx ? ctx.file : this.currentFile);
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'POST',
                    url: 'ajax.php?act=complete_upload',
                    data: {hash: ctx ? ctx.hash : that.input.hash, csrf_token: that.input.csrf_token},
                    dataType: 'json',
                    success: function(data) {
                        that.logFrontendTiming(timing);
                        that.updateCsrfToken(data);
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error:function(){
                        that.logFrontendTiming(timing, [{step: 'result', value: 'error'}]);
                        layer.msg('服务器错误');
                        reject('上传失败，请稍后再试或联系站长');
                    }
                });
            })
        },
        async uploadPart(file, chunk, ctx){
            var that = this;
            var tempTime = new Date().getTime();
            var oloaded = 0;
            var uploadCtx = ctx || {
                file: this.currentFile || file,
                hash: this.input.hash,
                size: this.input.size,
                chunkLoaded: {}
            };
            var timing = this.startFrontendTiming('upload_part chunk=' + chunk, uploadCtx.file || file);
            return new Promise((resolve, reject) => {
                var data = new FormData();
                data.append('file', file);
                data.append('hash', uploadCtx.hash);
                data.append('chunk', chunk);
                data.append('csrf_token', that.input.csrf_token);
                $.ajax({
                    type : "POST",
                    url : "ajax.php?act=upload_part",
                    data : data,
                    processData: false,
                    contentType: false,
                    dataType : 'json',
                    success : function(data) {
                        uploadCtx.chunkLoaded[chunk] = file.size || uploadCtx.chunkLoaded[chunk] || 0;
                        that.logFrontendTiming(timing);
                        that.updateCsrfToken(data);
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error : function(){
                        that.logFrontendTiming(timing, [{step: 'result', value: 'error'}]);
                        reject('上传失败，请稍后再试或联系站长');
                    },
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (e) {
                            uploadCtx.chunkLoaded[chunk] = e.loaded;
                            var loadedTotal = 0;
                            for(var loadedKey in uploadCtx.chunkLoaded){
                                loadedTotal += uploadCtx.chunkLoaded[loadedKey] || 0;
                            }
                            var progressRate = Math.round(loadedTotal / uploadCtx.size * 100);
                            if(progressRate>100)progressRate=100;
                            that.progress = progressRate;
                            if(that.batchTotal <= 1) that.totalProgress = progressRate;
                            if((e.lengthComputable && e.loaded >= e.total) || progressRate == 100) that.markFrontendUploadDone(timing);
                            that.updateQueueItem(uploadCtx.file, {
                                status: progressRate == 100 ? 'confirming' : 'uploading',
                                progress: progressRate
                            });
                            if(progressRate == 100) that.progress_tip = '等待服务器确认...';

                            var nowTime = new Date().getTime();
                            var pertime = (nowTime - tempTime) / 1000;
                            tempTime = nowTime;
                            var perload = e.loaded - oloaded;
                            oloaded = e.loaded;
                            var speed = that.size_format(perload/pertime)+'/s';
                            that.uploadspeed = speed;
                            that.updateQueueItem(uploadCtx.file, {speed: speed});
                        })
                        return xhr;
                    }
                });
            })
        },
        async uploadThird(url, postdata, file, ctx){
            var that = this;
            var tempTime = new Date().getTime();
            var oloaded = 0;
            var uploadCtx = ctx || {
                file: file,
                size: file.size,
                chunkLoaded: {}
            };
            var timing = this.startFrontendTiming('third_upload', file);
            return new Promise((resolve, reject) => {
                var data = new FormData();
                for(var key in postdata){
                    data.append(key, postdata[key]);
                }
                data.append('file', file);
                $.ajax({
                    type : "POST",
                    url : url,
                    data : data,
                    processData: false,
                    contentType: false,
                    dataType : 'html',
                    success : function(data) {
                        that.logFrontendTiming(timing);
                        resolve();
                    },
                    error : function(){
                        that.logFrontendTiming(timing, [{step: 'result', value: 'error'}]);
                        reject('上传失败，请稍后再试或联系站长');
                    },
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (e) {
                            var progressRate = Math.round(e.loaded / uploadCtx.size * 100);
                            if(progressRate>100)progressRate=100;
                            that.progress = progressRate;
                            if(that.batchTotal <= 1) that.totalProgress = progressRate;
                            if((e.lengthComputable && e.loaded >= e.total) || progressRate == 100) that.markFrontendUploadDone(timing);
                            that.updateQueueItem(uploadCtx.file, {
                                status: progressRate == 100 ? 'confirming' : 'uploading',
                                progress: progressRate
                            });
                            if(progressRate == 100) that.progress_tip = '等待服务器确认...';

                            var nowTime = new Date().getTime();
                            var pertime = (nowTime - tempTime) / 1000;
                            tempTime = nowTime;
                            var perload = e.loaded - oloaded;
                            oloaded = e.loaded;
                            var speed = that.size_format(perload/pertime)+'/s';
                            that.uploadspeed = speed;
                            that.updateQueueItem(uploadCtx.file, {speed: speed});
                        })
                        return xhr;
                    }
                });
            })
        },
        async getFileHash(file, ctx){
            var that = this;
            var timing = this.startFrontendTiming('file_md5', file);
            var indexText = ctx && ctx.total > 1 ? '['+ctx.index+'/'+ctx.total+'] ' : (this.batchTotal > 1 ? '['+this.batchIndex+'/'+this.batchTotal+'] ' : '');
            this.filename = indexText + '正在读取文件(0%)';

            return new Promise((resolve) => {
                var fileReader = new FileReader(),
                    blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice,
                    chunkSize = 2097152,
                    chunks = Math.ceil(file.size / chunkSize),
                    currentChunk = 0,
                    spark = new SparkMD5();

                if(chunks === 0){
                    this.updateQueueItem(file, {
                        status: 'reading',
                        progress: 100
                    });
                    this.logFrontendTiming(timing, [
                        {step: 'chunks', value: chunks},
                        {step: 'file_size', value: file.size}
                    ]);
                    resolve(SparkMD5.hashBinary(''));
                    return;
                }

                loadNext();

                fileReader.onload = function(e) {
                    spark.appendBinary(e.target.result);
                    currentChunk++;
                    var progressRate = Math.round(currentChunk / chunks * 100);
                    that.filename = indexText + '正在读取文件('+progressRate+'%)';
                    that.updateQueueItem(file, {
                        status: 'reading',
                        progress: progressRate
                    });
                    if (currentChunk < chunks) {
                        loadNext();
                    }
                    else {
                        that.logFrontendTiming(timing, [
                            {step: 'chunks', value: chunks},
                            {step: 'file_size', value: file.size}
                        ]);
                        resolve(spark.end());
                    }
                };

                function loadNext() {
                    var start = currentChunk * chunkSize,
                        end = start + chunkSize >= file.size ? file.size : start + chunkSize;
                    fileReader.readAsBinaryString(blobSlice.call(file, start, end));
                };
            })
        },
        uploadSuccess(hash){
            var lastTime = (new Date().getTime() - this.beginTime) / 1000;
            var jumpurl = "file.php?hash="+hash;
            if(this.input.ispwd && this.input.pwd!=''){
                jumpurl+='&pwd='+encodeURIComponent(this.input.pwd);
            }
            this.show_msg('上传成功！总用时：'+lastTime.toFixed(2)+'秒。正在跳转到文件查看页面...');
            setTimeout(function(){ window.location.href=jumpurl; }, 800);
        },
        showBatchResult(){
            var successCount = this.batchResults.length;
            var errorCount = this.batchErrors.length;
            var limitedCount = this.batchQueue.filter(function(item){ return item.status === 'limited'; }).length;
            var html = (this.batchTotal > 1 ? '批量上传完成' : '上传完成') + '：成功 '+successCount+' 个，失败 '+errorCount+' 个';
            if(limitedCount > 0) html += '，超出限制 '+limitedCount+' 个';
            html += '。';

            if(errorCount > 0){
                html += '<br><br><div class="text-left"><b>失败文件：</b>';
                for(var j = 0; j < this.batchErrors.length; j++){
                    var err = this.batchErrors[j];
                    html += '<br>'+this.escapeHtml(err.file.name)+' '+this.escapeHtml(err.msg);
                }
                html += '</div>';
            }

            this.progress = 0;
            this.totalProgress = 100;
            this.uploadspeed = '';
            this.filename = '';
            this.show_msg(html, errorCount > 0 ? 'warning' : 'success');
        },
        escapeHtml(text){
            return String(text).replace(/[&<>"']/g, function(ch){
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
            });
        },
        size_format(size){
            var units = 'B';
            if(size/1024>1){
                size = size/1024;
                units = 'KB';
            }
            if(size/1024>1){
                size = size/1024;
                units = 'MB';
            }
            if(size/1024>1){
                size = size/1024;
                units = 'GB';
            }
            return size.toFixed(2)+units;
        }
    }
})
